<?php

namespace App\Services;

use Illuminate\Support\Collection;

class ReportExport
{
    public function download(Collection $sales, string $format, string $from, string $to)
    {
        $rows = [['Venta', 'Fecha', 'Pago', 'Estado', 'Subtotal', 'Descuento', 'Total', 'Costo', 'Ganancia']];
        foreach ($sales as $s) {
            $rows[] = [(string) $s->id, $s->created_at, ['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia'][$s->payment], $s->status === 'completed' ? 'Completada' : 'Devuelta',
                $this->money($s->subtotal_cents), $this->money($s->discount_cents), $this->money($s->total_cents), $this->money($s->cost_cents), $s->status === 'completed' ? $this->money($s->total_cents - $s->cost_cents) : '0.00'];
        }
        $net = $sales->where('status', 'completed');
        $rows[] = ['Totales netos', '', '', '', '', '', $this->money($net->sum('total_cents')), $this->money($net->sum('cost_cents')), $this->money($net->sum('total_cents') - $net->sum('cost_cents'))];
        [$body,$mime] = match ($format) {
            'xlsx' => [$this->xlsx($rows), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'pdf' => [$this->pdf($rows, $from, $to), 'application/pdf'],
            default => [$this->csv($rows), 'text/csv; charset=UTF-8'],
        };
        Ledger::audit('report.exported', "Ventas $from a $to · $format");

        return response($body, 200, ['Content-Type' => $mime, 'Content-Disposition' => "attachment; filename=\"ventas-$from-$to.$format\"", 'Cache-Control' => 'no-store']);
    }

    private function money(int $c): string
    {
        return number_format($c / 100, 2, '.', '');
    }

    private function safe(mixed $s): string
    {
        $s = (string) $s;

        return preg_match('/^[=+@\-\t\r]/', $s) ? "'$s" : $s;
    }

    private function csv(array $rows): string
    {
        $f = fopen('php://temp', 'r+');
        fwrite($f, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($f, array_map($this->safe(...), $row), ';', '"', '');
        }
        rewind($f);
        $body = stream_get_contents($f);
        fclose($f);

        return $body;
    }

    private function xlsx(array $rows): string
    {
        $file = tempnam(storage_path('app'), 'report-');
        $z = new \ZipArchive;
        $z->open($file, \ZipArchive::OVERWRITE);
        try {
            $z->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $z->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $z->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Ventas" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="1" width="20" customWidth="1"/><col min="2" max="2" width="24" customWidth="1"/><col min="3" max="9" width="18" customWidth="1"/></cols><sheetData>';
            foreach ($rows as $i => $row) {
                $xml .= '<row r="'.($i + 1).'">';
                foreach ($row as $j => $value) {
                    $ref = chr(65 + $j).($i + 1);
                    if ($i > 0 && $j >= 4 && is_numeric($value)) {
                        $xml .= '<c r="'.$ref.'"><v>'.$value.'</v></c>';
                    } else {
                        $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
                    }
                } $xml .= '</row>';
            }
            $z->addFromString('xl/worksheets/sheet1.xml', $xml.'</sheetData></worksheet>');
            $z->close();

            return file_get_contents($file);
        } finally {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    private function pdf(array $rows, string $from, string $to): string
    {
        $lines = [];
        foreach (array_slice($rows, 1) as $r) {
            $lines[] = sprintf('%-13.13s %-19.19s %-13.13s %-12.12s %11s %12s', $r[0], $r[1], iconv('UTF-8', 'Windows-1252//TRANSLIT', $r[2]), $r[3], $r[6], $r[8]);
        }
        $chunks = array_chunk($lines, 44);
        if (! $chunks) {
            $chunks = [[]];
        }
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>'];
        $kids = [];
        foreach ($chunks as $i => $chunk) {
            $page = 4 + $i * 2;
            $content = $page + 1;
            $kids[] = "$page 0 R";
            $objects[$page] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R >> >> /Contents $content 0 R >>";
            $header = iconv('UTF-8', 'Windows-1252//TRANSLIT', request()->attributes->get('store')->name ?? 'Nexo Ventas');
            $all = [$header, "Reporte de ventas | $from a $to | PEN", 'Venta         Fecha               Pago          Estado             Total     Ganancia', str_repeat('-', 94), ...$chunk, '', 'Pagina '.($i + 1).' de '.count($chunks).' | Totales netos excluyen las ventas devueltas.'];
            $stream = "BT /F1 10 Tf 32 560 Td 11 TL\n";
            foreach ($all as $line) {
                $stream .= '('.str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $line).") Tj T*\n";
            }
            $stream .= 'ET';
            $objects[$content] = '<< /Length '.strlen($stream)." >>\nstream\n$stream\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$object\nendobj\n";
        }
        $start = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 $count\n0000000000 65535 f \n";
        for ($id = 1; $id < $count; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        return $pdf."trailer\n<< /Size $count /Root 1 0 R >>\nstartxref\n$start\n%%EOF";
    }
}
