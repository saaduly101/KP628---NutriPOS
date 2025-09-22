// --- small helpers ---
function highlight(text, query){
  const q = query.trim();
  if (!q) return text;
  const esc = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return text.replace(new RegExp(esc, 'ig'), (m)=>`<mark>${m}</mark>`);
}
const $ = (sel) => document.querySelector(sel);

function rowTemplate(data = {}) {
  const tr = document.createElement('tr');
  tr.className = 'row';
  tr.innerHTML = `
    <td>
      <div class="ac-wrap">
        <input type="text" placeholder="Start typing e.g. beef patty" class="name" value="${data.name || ''}" autocomplete="off"/>
        <div class="ac-list" style="display:none"></div>
      </div>
    </td>
    <td><input type="text" placeholder="AFCD Code e.g. F002893" class="code" value="${data.afcd_code || ''}"/></td>
    <td><input type="text" placeholder="Notes" class="notes" value="${data.notes || ''}"/></td>
    <td><input type="number" min="0" step="0.1" class="grams" value="${data.grams || ''}"/></td>
    <td><button type="button" class="btn danger del">Remove</button></td>
  `;
  // remove row
  tr.querySelector('.del').onclick = () => tr.remove();

  
  const nameInput = tr.querySelector('.name');
  const codeInput = tr.querySelector('.code');
  const list = tr.querySelector('.ac-list');
  let currentIndex = -1;
  let currentResults = [];

  const debounce = (fn, d = 200) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), d); }; };
  const closeList = () => { 
    list.style.display = 'none'; 
    list.innerHTML = ''; 
    currentIndex = -1; 
    currentResults = []; 
    list.closest('.ac-wrap').classList.remove('ac-active');
  };
  const openList  = () => { 
    list.style.display = 'block'; 
    list.closest('.ac-wrap').classList.add('ac-active');
  };

  const highlight = (text, query) => {
    const q = query.trim();
    if (!q) return text;
    const esc = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return text.replace(new RegExp(esc, 'ig'), (m)=>`<mark>${m}</mark>`);
  };

  const onPick = (item) => {
    nameInput.value = item.name;
    codeInput.value = item.code;
    closeList();
  };

  const renderList = (items, query) => {
    currentResults = items || [];
    if (!currentResults.length) { closeList(); return; }
    list.innerHTML = '';
    currentResults.forEach((it, idx) => {
      const div = document.createElement('div');
      div.className = 'ac-item';
      div.setAttribute('data-index', idx);
      div.innerHTML = `
        <div class="ac-info">
          <div class="ac-name">${highlight(it.name, query)}</div>
          <div class="ac-meta"> Code: <span class="pill">${it.code}</span>
          <div class="ac-meta">• ${it.energy_kj} kJ/100g • Prot ${it.protein_g}g • Fat ${it.fat_g}g • Carb ${it.carb_g}g</div>
          </div>
          ${it.tags && it.tags.length ? `<div class="ac-meta">Tags: ${it.tags.map(t=>`<span class="pill">${t}</span>`).join(' ')}</div>` : ''}
        </div>
        <div class="ac-meta digit">${it.category || ''}</div>
      `;
      div.onclick = () => onPick(it);
      list.appendChild(div);
    });
    openList();
    currentIndex = -1;
  };

  const doSearch = debounce(async () => {
    const q = nameInput.value.trim();
    if (q.length < 2) { closeList(); return; }
    try {
      const res = await fetch('../backend/afcd_search.php?q=' + encodeURIComponent(q));
      const data = await res.json();
      renderList(data.results || [], q);
    } catch {
      closeList();
    }
  }, 250);

  nameInput.addEventListener('input', doSearch);
  nameInput.addEventListener('focus', doSearch);
  nameInput.addEventListener('blur', () => setTimeout(closeList, 150));

  // keyboard nav
  nameInput.addEventListener('keydown', (e) => {
    const items = [...list.querySelectorAll('.ac-item')];
    if (!items.length) return;
    if (e.key === 'ArrowDown') { 
      e.preventDefault(); currentIndex = Math.min(items.length - 1, currentIndex + 1);
      items.forEach(i => i.classList.remove('active')); items[currentIndex].classList.add('active');
    } else if (e.key === 'ArrowUp') {
      e.preventDefault(); currentIndex = Math.max(0, currentIndex - 1);
      items.forEach(i => i.classList.remove('active')); items[currentIndex].classList.add('active');
    } else if (e.key === 'Enter') {
      e.preventDefault(); if (currentIndex >= 0) onPick(currentResults[currentIndex]);
    }
  });

  return tr;
}


function addRow(data){ $('#ingredients').appendChild(rowTemplate(data)); }

async function calculate(){
  const rows = [...document.querySelectorAll('#ingredients .row')];
  const ingredients = rows.map(r=> ({
    name: r.querySelector('.name').value.trim(),
    afcd_code: r.querySelector('.code').value.trim(),
    grams: parseFloat(r.querySelector('.grams').value||'0')
  })).filter(x=> (x.name||x.afcd_code) && x.grams>0);

  const res = await fetch('../backend/nutrition_calc.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ ingredients })
  });
  const data = await res.json();
  const result = $('#result');
  if (data.error){ result.style.display='block'; result.innerHTML = '<span style="color:red">'+data.error+'</span>'; return; }
  const t = data.totals;
  result.style.display='block';
  result.innerHTML = `
    <h3>Nutrition Totals</h3>
    <ul>
      <li>Energy: ${t["Energy (kJ)"].toFixed(1)} kJ</li>
      <li>Calories: ${t["Calories (kcal)"].toFixed(1)} kcal</li>
      <li>Protein: ${t["Protein (g)"].toFixed(2)} g</li>
      <li>Fat: ${t["Fat (g)"].toFixed(2)} g</li>
      <li>Carbohydrate: ${t["Carbohydrate (g)"].toFixed(2)} g</li>
      <li>Sugars: ${t["Sugars (g)"].toFixed(2)} g</li>
      <li>Sodium: ${t["Sodium (mg)"].toFixed(0)} mg</li>
    </ul>
    <div class="muted">Matched: ${data.matches.join(', ')||'—'}</div>
  `;
  
}

async function saveProduct(){
  const name = $('#productName').value.trim();
  const rows = [...document.querySelectorAll('#ingredients .row')];
  const ingredients = rows.map(r=> ({
    name: r.querySelector('.name').value.trim(),
    afcd_code: r.querySelector('.code').value.trim(),
    notes: r.querySelector('.notes').value.trim(),
    grams: parseFloat(r.querySelector('.grams').value||'0')
  })).filter(x=> x.name || x.afcd_code);

  const idHash = location.hash && location.hash.substring(1);
  const payload = { id: idHash || null, name, ingredients };
  const res = await fetch('../backend/products_save.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  });
  const data = await res.json();
  if (data.error){ alert(data.error); return; }
  location.hash = data.product.id;
  alert('Saved!');
}

async function loadProduct(id){
  const res = await fetch('../backend/products_get.php?id='+encodeURIComponent(id));
  const data = await res.json();
  return data.product;
}

// --- POS: create order from current rows ---
async function createOrder(){
  const rows = [...document.querySelectorAll('#ingredients .row')];
  const items = rows.map(r=> ({
    name: r.querySelector('.name').value.trim(),
    afcd_code: r.querySelector('.code').value.trim(),
    grams: parseFloat(r.querySelector('.grams').value||'0'),
    qty: 1
  })).filter(x=> (x.name||x.afcd_code) && x.grams>0);

  if (!items.length){ alert('Add at least one ingredient'); return; }

  const customer_email = $('#custEmail').value.trim();

  const res = await fetch('../backend/orders_create.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ customer_email, items })
  });

  const raw = await res.text();
  let data;
  try { data = JSON.parse(raw); }
  catch { 
    $('#orderResult').innerHTML = `<div style="color:#c00">Invalid JSON from orders_create.php</div>`;
    console.error('orders_create.php raw:', raw);
    return;
  }

  const el = $('#orderResult');
  if (data.error){
    el.innerHTML = `<div style="color:#c00">Error: ${data.error}</div>`;
    return;
  }

  const id = data.order_id;
  const t = data.totals || {};

  // Build human-friendly QR payload (great for phone scanners)
  const payload = [
    `NutriPOS #${id}`,
    `Energy: ${Number(t["Energy (kJ)"]||0).toFixed(1)} kJ (${Number(t["Calories (kcal)"]||0).toFixed(1)} kcal)`,
    `Protein: ${Number(t["Protein (g)"]||0).toFixed(2)} g`,
    `Fat: ${Number(t["Fat (g)"]||0).toFixed(2)} g`,
    `Carb: ${Number(t["Carbohydrate (g)"]||0).toFixed(2)} g`,
    `Sugars: ${Number(t["Sugars (g)"]||0).toFixed(2)} g`,
    `Sodium: ${Number(t["Sodium (mg)"]||0).toFixed(0)} mg`
  ].join('\n');

  el.innerHTML = `
    <h3>Order #${id} created</h3>
    <ul>
      <li>Energy: ${t["Energy (kJ)"]} kJ (${t["Calories (kcal)"]} kcal)</li>
      <li>Protein: ${t["Protein (g)"]} g</li>
      <li>Fat: ${t["Fat (g)"]} g</li>
      <li>Carb: ${t["Carbohydrate (g)"]} g</li>
      <li>Sugars: ${t["Sugars (g)"]} g</li>
      <li>Sodium: ${t["Sodium (mg)"]} mg</li>
    </ul>
    <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
      <p style="margin:0"><a href="receipt.html?id=${id}" target="_blank" class="btn ghost">Open receipt (QR)</a></p>

    </div>
    <form method = "post" action = "../backend/email_receipt.php" 
    <div id="emailBlock" class="flex" style="margin-top:10px">
      <input type = "email" id="emailTo" placeholder="customer@example.com" style="flex:1;padding:8px" value="${customer_email||''}">
      <button id="emailBtn" class="btn" type = "button">Email Receipt</button>
      <span id="emailStatus" class="muted"></span>
    </div>

`;


alert(payload)
const emailBtn = document.getElementById("emailBtn");
const emailInput = document.getElementById("emailTo");
const emailStatus = document.getElementById("emailStatus");

emailBtn.onclick = async () => {
  const customerEmail = emailInput.value.trim();

  //  payload
  const info = {
    email: customerEmail,
    subject: "NutriPOS receipt: " + id,
    comment: payload   // <-- nutrition/order summary string
  };
  try {
    const res = await fetch("../backend/email_receipt.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(info)
    });
    const out = await res.text();
    emailStatus.textContent = out; // raw PHP echo for now
  } catch (e) {
    console.error(e);
    emailStatus.textContent = "Error sending request";
  }
};

  



function makeCode () {		

  // Clear old QR
  document.getElementById('qrcode').innerHTML = '';

  // Generate new QR
  var text = "http://localhost/NutriPOS/NutriPOS/KP628---NutriPOS/NUTRIPOS/public/receipt.html?id=";
  var link = text + id
  // alert(link)
	// qrcode.makeCode(link);

  new QRCode(document.getElementById("qrcode"), {
    text: link,
    width: 256,
    height: 256
  });

  
}


makeCode();

$("#text").
  on("blur", function () {
    makeCode();
  }).
  on("keydown", function (e) {
    if (e.keyCode == 13) {
      makeCode();
    }
  })



  // Email button
  const btn = $('#emailBtn');
  if (btn){
    btn.onclick = async ()=>{
      const to = $('#emailTo').value.trim();
      if (!to){ alert('Enter an email'); return; }
      const fd = new FormData();
      fd.append('order_id', String(id));
      fd.append('to', to);
      const res2 = await fetch('../backend/email_receipt.php', { method:'POST', body: fd });
      let r; try { r = await res2.json(); } catch(e){}
      const st = $('#emailStatus');
      if (r && r.ok){ st.textContent = 'Sent ✔'; st.style.color = 'green'; }
      else { st.textContent = (r && r.error) ? r.error : 'Send failed'; st.style.color = 'red'; }
    };
  }
}


// --- boot ---
document.addEventListener('DOMContentLoaded', async ()=>{
  $('#addRow').onclick = ()=> addRow({});
  $('#calcBtn').onclick = calculate;
  $('#saveBtn').onclick = saveProduct;
  $('#createOrderBtn').onclick = createOrder;

  const idHash = location.hash && location.hash.substring(1);
  if (idHash){
    const p = await loadProduct(idHash);
    $('#productName').value = p.name || '';
    (p.ingredients||[]).forEach(addRow);
  } else {
    // sensible defaults (can tweak)
    addRow({name:'Beef Patty (cooked)', grams:120});
    addRow({name:'Hamburger Bun', grams:80});
    addRow({name:'Tomato', grams:30});
    addRow({name:'BBQ Sauce', grams:15});
    addRow({name:'Mayonnaise', grams:20});
    addRow({name:'Cheddar Cheese', grams:30});
  }
});


