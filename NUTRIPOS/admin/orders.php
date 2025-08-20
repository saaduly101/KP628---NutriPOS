<?php
// admin/orders.php
require_once __DIR__.'/../backend/auth.php';
auth_require_admin();
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8"/>
    <title>Admin - Orders</title>
    <link rel="stylesheet" href="../public/style.css" />
  </head>
<body>
  <nav class="navbar">
      <div class="navbar-container">
        <div class="logo-dashboard"> 
          <a href="#" class="logo">NutriPOS</a>
          <span class="admin-dashboard">Admin Dashboard</span>
        </div>
        <ul class="navbar-links">
          <li><a href="dashboard.php" class="nav-button">Dashboard</a></li>
          <li><a href="orders.php" class="nav-button active">Order History</a></li>
        </ul>
        <div class="user-section">
          <span class="admin"><?php echo htmlspecialchars($_SESSION['email']); ?></span>
          <a href="logout.php"><button class="logout-btn">Logout</button></a>
        </div>
      </div>
    </nav>

  <h2>Order History</h2>
  <div class="toolbar">
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
