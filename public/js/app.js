document.addEventListener('DOMContentLoaded',()=>{
 const applyTheme=(theme,persist=false)=>{
  const selected=theme==='dark'?'dark':'light';
  document.documentElement.dataset.theme=selected;
  document.documentElement.style.colorScheme=selected;
  document.querySelectorAll('[data-theme-value]').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.themeValue===selected)));
  if(persist){try{localStorage.setItem('nexo-theme',selected);}catch{/* The selector also works when browser storage is unavailable. */}}
 };
 applyTheme(document.documentElement.dataset.theme);
 document.querySelectorAll('[data-theme-value]').forEach(button=>button.addEventListener('click',()=>applyTheme(button.dataset.themeValue,true)));
 window.addEventListener('storage',event=>{if(event.key==='nexo-theme')applyTheme(event.newValue);});
 document.querySelector('[data-menu]')?.addEventListener('click',()=>document.getElementById('sidebar').classList.toggle('open'));
 document.addEventListener('click',e=>{if(!e.target.closest('.sidebar')&&!e.target.closest('[data-menu]'))document.getElementById('sidebar')?.classList.remove('open');});
 document.querySelectorAll('[data-password]').forEach(button=>button.addEventListener('click',()=>{const input=button.parentElement.querySelector('input');input.type=input.type==='password'?'text':'password';button.textContent=input.type==='password'?'Ver':'Ocultar';}));
 document.querySelectorAll('[data-print]').forEach(button=>button.addEventListener('click',()=>window.print()));
 document.querySelectorAll('[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{if(!window.confirm(form.dataset.confirm))e.preventDefault();}));
 const pos=document.getElementById('pos');if(!pos)return;
 const data=JSON.parse(document.getElementById('pos-data').textContent),sale=pos.dataset.mode==='sales';
 const products=data.products,map=new Map(products.map(p=>[Number(p.id),p])),cart=new Map();
 for(const item of Object.values(data.items||{})){const p=map.get(Number(item.product_id));if(p)cart.set(Number(p.id),{product:p,quantity:Number(item.quantity)||1,cost:String(item.cost??(p.cost_cents/100).toFixed(2))});}
 const grid=document.getElementById('product-grid'),list=document.getElementById('cart-items'),search=document.getElementById('product-search');
 const money=c=>'S/ '+(c/100).toLocaleString('es-PE',{minimumFractionDigits:2,maximumFractionDigits:2});
 const cents=v=>{const [a,b='']=String(v||'0').split('.');return (parseInt(a,10)||0)*100+(parseInt(b.padEnd(2,'0').slice(0,2),10)||0);};
 const node=(tag,cls,text)=>{const el=document.createElement(tag);if(cls)el.className=cls;if(text!==undefined)el.textContent=text;return el;};
 function drawProducts(){grid.replaceChildren();const query=search.value.trim().toLocaleLowerCase('es');let count=0;
  for(const p of products){if(![p.name,p.sku,p.barcode].some(v=>String(v||'').toLocaleLowerCase('es').includes(query)))continue;
   const button=node('button','pos-product');button.type='button';button.disabled=sale&&Number(p.stock)===0;
   button.append(node('small',null,p.sku),node('strong',null,p.name),node('small',null,`${p.stock} en stock`),node('span','price',sale?money(p.price_cents):`Costo ${money(p.cost_cents)}`));
   button.addEventListener('click',()=>add(p));grid.append(button);count++;
  }if(!count)grid.append(node('p','empty','No se encontraron productos.'));
 }
 function add(p){const id=Number(p.id),existing=cart.get(id);if(sale&&existing&&existing.quantity>=Number(p.stock)){window.alert('Alcanzaste el stock disponible.');return;}if(existing)existing.quantity++;else cart.set(id,{product:p,quantity:1,cost:(p.cost_cents/100).toFixed(2)});drawCart();}
 function totals(){let subtotal=0;for(const line of cart.values())subtotal+=(sale?Number(line.product.price_cents):cents(line.cost))*line.quantity;
  const discount=sale?cents(document.getElementById('discount').value):0,total=Math.max(0,subtotal-discount);
  document.getElementById('subtotal').textContent=money(subtotal);document.getElementById('total').textContent=money(total);
  if(sale){const cash=document.getElementById('payment').value==='cash';document.getElementById('received-field').hidden=!cash;document.getElementById('received').required=cash;document.getElementById('change').textContent=money(cash?Math.max(0,cents(document.getElementById('received').value)-total):0);}
 }
 function drawCart(){list.replaceChildren();let index=0;
  if(!cart.size){const empty=node('div','cart-empty','Tu carrito está vacío. Agrega un producto para comenzar.');list.append(empty);}
  for(const [id,line]of cart){const row=node('div','order-item'),info=node('div');info.append(node('strong',null,line.product.name),node('small',null,sale?money(line.product.price_cents)+' por unidad':'Costo unitario (S/)'));
   const hidden=node('input');hidden.type='hidden';hidden.name=`items[${index}][product_id]`;hidden.value=id;info.append(hidden);
   if(!sale){const cost=node('input','purchase-cost');cost.type='number';cost.name=`items[${index}][cost]`;cost.value=line.cost;cost.min='0';cost.max='99999999.99';cost.step='0.01';cost.required=true;cost.setAttribute('aria-label','Costo de '+line.product.name);cost.addEventListener('input',()=>{line.cost=cost.value;totals();});info.append(cost);}
   const quantity=node('input');quantity.type='number';quantity.name=`items[${index}][quantity]`;quantity.value=line.quantity;quantity.min='1';quantity.max=sale?line.product.stock:1000000;quantity.step='1';quantity.required=true;quantity.setAttribute('aria-label','Cantidad de '+line.product.name);quantity.addEventListener('input',()=>{line.quantity=Number(quantity.value)||0;totals();});
   const remove=node('button',null,'×');remove.type='button';remove.setAttribute('aria-label','Quitar '+line.product.name);remove.addEventListener('click',()=>{cart.delete(id);drawCart();});row.append(info,quantity,remove);list.append(row);index++;
  }totals();
 }
 search.addEventListener('input',drawProducts);search.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();const code=search.value.trim();const p=products.find(p=>p.barcode===code||p.sku===code);if(p&&(!sale||Number(p.stock)>0)){add(p);search.value='';drawProducts();}}});
 ['discount','payment','received'].forEach(id=>document.getElementById(id)?.addEventListener('input',totals));
 document.getElementById('order-form').addEventListener('submit',e=>{if(!cart.size){e.preventDefault();window.alert('Agrega al menos un producto.');return;}if(sale){let subtotal=0;for(const line of cart.values())subtotal+=Number(line.product.price_cents)*line.quantity;const discount=cents(document.getElementById('discount').value);if(discount>subtotal){e.preventDefault();window.alert('El descuento supera el subtotal.');return;}if(document.getElementById('payment').value==='cash'&&cents(document.getElementById('received').value)<subtotal-discount){e.preventDefault();window.alert('El efectivo recibido es menor que el total.');return;}}document.getElementById('confirm-order').disabled=true;});
 drawProducts();drawCart();
});
