function highlight(text, query){
  const q = query.trim();
  if (!q) return text;
  const esc = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return text.replace(new RegExp(esc, 'ig'), (m)=>`<mark>${m}</mark>`);
}

function rowTemplate(data={}){
  const r = document.createElement('div');
  r.className = 'grid row';
  r.innerHTML = `
    <div class="ac-wrap">
      <input placeholder="Start typing e.g. beef patty" class="name" value="${data.name||''}" autocomplete="off"/>
      <div class="ac-list" style="display:none"></div>
    </div>
    <input placeholder="AFCD Code e.g. F002893" class="code" value="${data.afcd_code||''}"/>
    <input placeholder="Notes" class="notes" value="${data.notes||''}"/>
    <input type="number" min="0" step="0.1" class="grams" value="${data.grams||''}"/>
    <button class="btn ghost del">Remove</button>
  `;
  r.querySelector('.del').onclick = ()=> r.remove();

  // Autocomplete
  const nameInput = r.querySelector('.name');
  const codeInput = r.querySelector('.code');
  const list = r.querySelector('.ac-list');
  let currentIndex = -1;
  let currentResults = [];

  const debounce = (fn, d=200)=>{
    let t; return (...args)=>{ clearTimeout(t); t=setTimeout(()=>fn(...args), d); };
  };

  const closeList = ()=>{ list.style.display='none'; list.innerHTML=''; currentIndex=-1; currentResults=[]; };
  const openList = ()=>{ list.style.display='block'; };

  const onPick = (item)=>{
    nameInput.value = item.name;
    codeInput.value = item.code;
    closeList();
  };

  const renderList = (items, query)=>{
    currentResults = items || [];
    if (!currentResults.length){ closeList(); return; }
    list.innerHTML = '';
    currentResults.forEach((it, idx)=>{
      const div = document.createElement('div');
      div.className = 'ac-item';
      div.setAttribute('data-index', idx);
      div.innerHTML = `
        <div>
          <div class="ac-name">${highlight(it.name, query)}</div>
          <div class="ac-meta">Code: <span class="pill">${it.code}</span> • ${it.energy_kj} kJ/100g • Prot ${it.protein_g}g • Fat ${it.fat_g}g • Carb ${it.carb_g}g</div>
          ${it.tags && it.tags.length ? `<div class="ac-meta">Tags: ${it.tags.map(t=>`<span class="pill">${t}</span>`).join(' ')}</div>` : ''}
        </div>
        <div class="ac-meta">${it.category||''}</div>
      `;
      div.onclick = ()=> onPick(it);
      list.appendChild(div);
    });
    openList();
    currentIndex = -1;
  };

  const doSearch = debounce(async ()=>{
    const q = nameInput.value.trim();
    if (q.length < 2){ closeList(); return; }
    try{
      const res = await fetch('../backend/afcd_search.php?q='+encodeURIComponent(q));
      const data = await res.json();
      renderList(data.results || [], q);
    }catch(e){ closeList(); }
  }, 250);

  nameInput.addEventListener('input', doSearch);
  nameInput.addEventListener('focus', doSearch);
  nameInput.addEventListener('blur', ()=> setTimeout(closeList, 150));

  // Keyboard navigation
  nameInput.addEventListener('keydown', (e)=>{
    const items = [...list.querySelectorAll('.ac-item')];
    if (!items.length) return;
    if (e.key === 'ArrowDown'){ e.preventDefault(); currentIndex = Math.min(items.length-1, currentIndex+1); items.forEach(i=>i.classList.remove('active')); items[currentIndex].classList.add('active'); }
    else if (e.key === 'ArrowUp'){ e.preventDefault(); currentIndex = Math.max(0, currentIndex-1); items.forEach(i=>i.classList.remove('active')); items[currentIndex].classList.add('active'); }
    else if (e.key === 'Enter'){ e.preventDefault(); if (currentIndex>=0) { onPick(currentResults[currentIndex]); } }
  });

  return r;
}

async function listProducts(){
  const tb = document.getElementById('list');
  if (!tb) return;
  const res = await fetch('../backend/products_list.php');
  const data = await res.json();
  tb.innerHTML = '';
   
  (data.products||[]).forEach(p=>{
    const ingredients = p.ingredients || [];

    let ingredientsHtml = '';
    if (ingredients.length > 0) {
      // Show first 3 ingredients, each on its own row
      ingredients.slice(0, 3).forEach(i => {
        ingredientsHtml += `<div class="ingredient-items">${i.name}${i.grams ? `  (${i.grams}g)` : ''}</div>`;
      });

      // Add summary if there are more
      if (ingredients.length > 3) {
        ingredientsHtml += `<div style="color:#666;">+${ingredients.length - 3} more ingredients</div>`;
      }
    } else {
      ingredientsHtml = '<div style="color:#666;">No ingredients</div>';
    } 

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><a href="custom_pos_builder.php#${p.id}" style="color: black">${p.name}</a></td>
      <td class="product-ingredients">
        ${ingredientsHtml}
      </td>
      <td>${new Date(p.updated_at).toLocaleString()}</td>
      <td>
        <button type="button" class="btn danger del" data-id="${p.id}">Remove</button>
      </td>
    `;
    tr.querySelector('button').onclick = async (e)=>{
      const id = e.target.getAttribute('data-id');
      if (!confirm('Delete this product?')) return;
      await fetch('../backend/products_delete.php?id='+encodeURIComponent(id));
      listProducts();
    };
    tb.appendChild(tr);
  });
}

async function loadProduct(id){
  const res = await fetch('../backend/products_get.php?id='+encodeURIComponent(id));
  const data = await res.json();
  return data.product;
}

async function saveProduct(){
  const name = document.getElementById('productName').value.trim();
  const ingredientsEl = document.getElementById('ingredients');
  const rows = [...ingredientsEl.querySelectorAll('.row')];
  const ingredients = rows.map(r=> ({
    name: r.querySelector('.name').value.trim(),
    afcd_code: r.querySelector('.code').value.trim(),
    notes: r.querySelector('.notes').value.trim(),
    grams: parseFloat(r.querySelector('.grams').value||'0')
  })).filter(x=>x.name || x.afcd_code);
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

async function calculate(){
  const ingredientsEl = document.getElementById('ingredients');
  const rows = [...ingredientsEl.querySelectorAll('.row')];
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
  const result = document.getElementById('result');
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
  document.getElementById('qrcode').innerHTML = '';
  new QRCode(document.getElementById('qrcode'), JSON.stringify({name: document.getElementById('productName').value, totals: t}));
}

// function addRow(data){ document.getElementById('ingredients').appendChild(rowTemplate(data)); }

function addRow(data = {}) {
  document.querySelector('#ingredients').appendChild(rowTemplate(data));
}

document.addEventListener('DOMContentLoaded', async ()=>{
  const addBtn = document.getElementById('addRow');
  if (addBtn){ addBtn.onclick = ()=> addRow({}); }
  const calcBtn = document.getElementById('calcBtn');
  if (calcBtn){ calcBtn.onclick = calculate; }
  const saveBtn = document.getElementById('saveBtn');
  if (saveBtn){ saveBtn.onclick = saveProduct; }

  const idHash = location.hash && location.hash.substring(1);
  if (idHash){
    const p = await loadProduct(idHash);
    document.getElementById('productName').value = p.name || '';
    (p.ingredients||[]).forEach(addRow);
  } else {
    addRow({name:'Beef', grams:120});
    addRow({name:'Bread roll', grams:80});
    addRow({name:'Tomato', grams:30});
    addRow({name:'beetroot', grams:25});
    addRow({name:'Iceberg lettuce', grams:15});
    addRow({name:'Tomato Sauce', grams:15});
    addRow({name:'BBQ Sauce', grams:15});
    addRow({name:'Mayonnaise', grams:20});
    addRow({name:'Cheese Slice', grams:30});
  }
});
