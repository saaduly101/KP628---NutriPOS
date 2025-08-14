<?php
// admin/orders.php
require_once __DIR__.'/../backend/auth.php';
auth_require_admin();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>NutriPOS – Orders</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; padding: 16px; }
    h1 { margin: 0 0 12px; }
    table { border-collapse: collapse; width: 100%; max-width: 1100px; }
    th, td { border-bottom: 1px solid #eee; padding: 8px 10px; font-size: 14px; }
    th { text-align: left; background: #fafafa; }
    .muted { color: #777; }
    .right { text-align: right; }
    .toolbar { margin: 10px 0 14px; display: flex; gap: 8px; align-items: center; }
    .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; background:#f2f2f2; font-size: 12px; }
  </style>
</head>
<body>
  <h1>Orders</h1>
  <div class="toolbar">
    <a href="dashboard.php">← Back to Dashboard</a>
    <span class="pill" id="count">Loading…</span>
  </div>

  <table id="orders">
    <thead>
      <tr>
        <th>ID</th>
        <th>Created</th>
        <th>Email</th>
        <th class="right">kcal</th>
        <th class="right">P</th>
        <th class="right">F</th>
        <th class="right">C</th>
        <th class="right">Na</th>
        <th>Receipt</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>

  <p id="empty" class="muted" style="display:none">No orders yet. Create one via the POS simulator.</p>

  <script>
    async function loadOrders(){
      const res = await fetch('/NutriPOS/backend/order_list.php', {
        cache: 'no-store',
        credentials: 'same-origin'
      });

      let data;
      try { data = await res.json(); } catch (e) { data = {error:'Invalid JSON'}; }
      const tbody = document.querySelector('#orders tbody');
      const countEl = document.getElementById('count');
      const emptyEl = document.getElementById('empty');
      tbody.innerHTML = '';

      if (data.error){
        countEl.textContent = 'Error';
        tbody.innerHTML = `<tr><td colspan="9" style="color:red">${data.error}</td></tr>`;
        return;
      }

      const orders = Array.isArray(data.orders) ? data.orders : [];
      countEl.textContent = `${orders.length} orders`;

      if (orders.length === 0){
        emptyEl.style.display = '';
        return;
      }
      emptyEl.style.display = 'none';

      for (const o of orders){
        const tr = document.createElement('tr');
        const created = (o.created_at || '').replace('T',' ').replace('Z','');
        const rlink = `../public/receipt.html?id=${encodeURIComponent(o.id)}`;
        tr.innerHTML = `
          <td>${o.id}</td>
          <td>${created}</td>
          <td>${o.customer_email ? escapeHtml(o.customer_email) : '<span class="muted">–</span>'}</td>
          <td class="right">${fmt(o.calories_kcal)}</td>
          <td class="right">${fmt(o.protein_g)}</td>
          <td class="right">${fmt(o.fat_g)}</td>
          <td class="right">${fmt(o.carb_g)}</td>
          <td class="right">${fmt(o.sodium_mg)}</td>
          <td><a href="${rlink}" target="_blank">View</a></td>
        `;
        tbody.appendChild(tr);
      }
    }

    function fmt(x){
      const n = Number(x);
      if (Number.isFinite(n)) return n.toLocaleString(undefined, {maximumFractionDigits: 2});
      return '';
      }
    function escapeHtml(s){
      return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }

    loadOrders();
  </script>

  <script>
  async function loadOrders(){
    const tbody = document.querySelector('#orders tbody');
    const countEl = document.getElementById('count');
    const emptyEl = document.getElementById('empty');

    const res = await fetch('../backend/orders_list.php', {cache:'no-store'});
    const text = await res.text();

    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      countEl.textContent = 'Error';
      tbody.innerHTML = `<tr><td colspan="9" style="color:red">Invalid JSON<br><pre style="white-space:pre-wrap">${escapeHtml(text)}</pre></td></tr>`;
      return;
    }

    if (data.error){
      countEl.textContent = 'Error';
      tbody.innerHTML = `<tr><td colspan="9" style="color:red">${escapeHtml(data.error)}${data.details ? '<br><pre>'+escapeHtml(data.details)+'</pre>' : ''}</td></tr>`;
      return;
    }

    const orders = Array.isArray(data.orders) ? data.orders : [];
    countEl.textContent = `${orders.length} orders`;
    tbody.innerHTML = '';
    if (orders.length === 0){
      emptyEl.style.display = '';
      return;
    }
    emptyEl.style.display = 'none';

    for (const o of orders){
      const tr = document.createElement('tr');
      const created = (o.created_at || '').replace('T',' ').replace('Z','');
      const rlink = `../public/receipt.html?id=${encodeURIComponent(o.id)}`;
      tr.innerHTML = `
        <td>${o.id}</td>
        <td>${created}</td>
        <td>${o.customer_email ? escapeHtml(o.customer_email) : '<span class="muted">–</span>'}</td>
        <td class="right">${fmt(o.calories_kcal)}</td>
        <td class="right">${fmt(o.protein_g)}</td>
        <td class="right">${fmt(o.fat_g)}</td>
        <td class="right">${fmt(o.carb_g)}</td>
        <td class="right">${fmt(o.sodium_mg)}</td>
        <td><a href="${rlink}" target="_blank">View</a></td>
      `;
      tbody.appendChild(tr);
    }
  }

  function fmt(x){
    const n = Number(x);
    return Number.isFinite(n) ? n.toLocaleString(undefined, {maximumFractionDigits: 2}) : '';
  }
  function escapeHtml(s){
    return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }

  loadOrders();
</script>

</body>
</html>
