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
    <td><input type="number" min="0" step="0.1" class="grams" value="${data.grams || ''}" required/></td>
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
  document.getElementById('nutritionInfo').style.display = 'none';
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
    <div class="nutrition-energy">
      <span class="energy-label">Energy</span>
      <span class="energy-value">${t["Energy (kJ)"].toFixed(0)} kJ</span>
    </div>
    
    <div class="nutrition-macros">
      <div class="macro-item protein">
        <span class="macro-value">${t["Protein (g)"].toFixed(0)}g</span>
        <span class="macro-label">Protein</span>
      </div>
      <div class="macro-item calories">
        <span class="macro-value">${t["Calories (kcal)"].toFixed(0)} kcal</span>
        <span class="macro-label">Calories</span>
      </div>
    </div>
    
    <div class="nutrition-details">
      <div class="detail-row">
        <span class="detail-label">Saturated Fat</span>
        <span class="detail-value">${t["Fat (g)"] ? (t["Fat (g)"] * 0.3).toFixed(0) : 0}g</span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Carbohydrates</span>
        <span class="detail-value">${t["Carbohydrate (g)"].toFixed(0)}g</span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Sugars</span>
        <span class="detail-value">${t["Sugars (g)"].toFixed(0)}g</span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Sodium</span>
        <span class="detail-value">${t["Sodium (mg)"].toFixed(0)}mg</span>
      </div>
    </div>
    
    <div class="nutrition-footer">
      <div class="muted">Matched: ${data.matches.join(', ')||'—'}</div>
    </div>
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

}


// --- boot ---
document.addEventListener('DOMContentLoaded', async ()=>{
  $('#addRow').onclick = ()=> addRow({});
  $('#calcBtn').onclick = calculate;
  $('#saveBtn').onclick = saveProduct;
const createBtn = $('#createOrderBtn'); if (createBtn) createBtn.onclick = createOrder;

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


