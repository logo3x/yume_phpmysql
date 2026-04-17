const tabs = ["clientes", "inventario", "compras", "ventas", "envios", "caja", "reportes"];
let salesChart, profitChart, topProductsChart, unifiedChart;
let allProducts = [], allPurchases = [], allSales = [], allClients = [], allShipments = [], allCashMovements = [];
let userPermissions = {};
let isAdmin = false;

const BASE_PATH = '/';
const API_BASE = BASE_PATH + 'index.php?_route=api/';

function applyPermissions(perms, admin) {
  userPermissions = perms || {};
  isAdmin = admin;
  
  // Ocultar tabs según permisos
  tabs.forEach(tab => {
    const btn = document.querySelector(`[data-tab="${tab}"]`);
    if (btn) {
      const p = userPermissions[tab];
      if (p && !p.view && !isAdmin) {
        btn.style.display = 'none';
      } else {
        btn.style.display = '';
      }
    }
  });
}

function canCreate(module) {
  return isAdmin || (userPermissions[module] && userPermissions[module].create);
}

function canEdit(module) {
  return isAdmin || (userPermissions[module] && userPermissions[module].edit);
}

function canDelete(module) {
  return isAdmin || (userPermissions[module] && userPermissions[module].delete);
}

function today() {
  return new Date().toISOString().slice(0, 10);
}

function money(n) {
  return `$ ${Number(n || 0).toLocaleString("es-CO", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function showToast(message, type = "success") {
  const container = document.getElementById("toastContainer");
  const toast = document.createElement("div");
  toast.className = `toast ${type}`;
  toast.innerHTML = `<span>${type === "success" ? "✅" : type === "error" ? "❌" : "ℹ️"}</span> ${message}`;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.animation = "slideIn 0.3s ease reverse";
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

function table(columns, rows) {
  if (!rows || rows.length === 0) {
    return `<div class="empty-state"><div class="icon">📭</div><p>No hay datos registrados</p></div>`;
  }
  const head = columns.map(c => `<th>${c}</th>`).join("");
  const body = rows.map(r => `<tr>${r.map(v => `<td>${v ?? ""}</td>`).join("")}</tr>`).join("");
  return `<table><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table>`;
}

function renderPagination(totalPages, currentPage, containerId, onPageChange) {
  const container = document.getElementById(containerId);
  if (!container || totalPages <= 1) {
    if (container) container.innerHTML = "";
    return;
  }
  
  let html = "";
  const maxVisible = 5;
  let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
  let endPage = Math.min(totalPages, startPage + maxVisible - 1);
  
  if (endPage - startPage < maxVisible - 1) {
    startPage = Math.max(1, endPage - maxVisible + 1);
  }
  
  html += `<button class="btn btn-sm btn-secondary" onclick="(${onPageChange.toString()})(${currentPage > 1 ? currentPage - 1 : 1})" ${currentPage === 1 ? "disabled" : ""}>«</button>`;
  
  if (startPage > 1) {
    html += `<button class="btn btn-sm btn-secondary" onclick="(${onPageChange.toString()})(1)">1</button>`;
    if (startPage > 2) html += `<span style="padding:0 8px;">...</span>`;
  }
  
  for (let i = startPage; i <= endPage; i++) {
    html += `<button class="btn btn-sm ${i === currentPage ? "btn-primary" : "btn-secondary"}" onclick="(${onPageChange.toString()})(${i})">${i}</button>`;
  }
  
  if (endPage < totalPages) {
    if (endPage < totalPages - 1) html += `<span style="padding:0 8px;">...</span>`;
    html += `<button class="btn btn-sm btn-secondary" onclick="(${onPageChange.toString()})(${totalPages})">${totalPages}</button>`;
  }
  
  html += `<button class="btn btn-sm btn-secondary" onclick="(${onPageChange.toString()})(${currentPage < totalPages ? currentPage + 1 : totalPages})" ${currentPage === totalPages ? "disabled" : ""}>»</button>`;
  
  container.innerHTML = html;
}

async function api(url, options = {}) {
  try {
    let fullUrl;
    if (url.startsWith('/api/')) {
      // Route through index.php to api
      const apiPath = url.replace('/api/', '');
      fullUrl = BASE_PATH + 'index.php?_route=' + apiPath;
    } else if (url.startsWith('/')) {
      fullUrl = BASE_PATH + url;
    } else {
      fullUrl = BASE_PATH + 'index.php?_route=' + url;
    }
    const res = await fetch(fullUrl, { credentials: "include", ...options });
    const contentType = res.headers.get("content-type");
    let data;
    
    if (contentType && contentType.includes("application/json")) {
      data = await res.json();
    } else {
      const text = await res.text();
      throw new Error(`Error ${res.status}: Respuesta inesperada del servidor`);
    }
    
    if (!res.ok) {
      throw new Error(data.error || `Error ${res.status}`);
    }
    return data;
  } catch (err) {
    if (err.message !== "Error en la solicitud") {
      showToast(err.message, "error");
    }
    throw err;
  }
}

function setupTabs() {
  const tabsContainer = document.getElementById("tabs");
  tabsContainer.addEventListener("click", e => {
    const btn = e.target.closest(".tab-btn");
    if (!btn) return;
    const tab = btn.dataset.tab;
    if (!tab) return;
    switchToTab(tab);
  });
}

function switchToTab(tab) {
  document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
  document.querySelectorAll(".tab").forEach(s => s.classList.remove("active"));
  document.querySelector(`[data-tab="${tab}"]`)?.classList.add("active");
  document.getElementById(tab)?.classList.add("active");
  if (tab === "reportes") refreshReports();
  if (tab === "admin") loadAdminData();
}

// ==================== PRODUCTOS ====================
async function refreshProducts() {
  try {
    allProducts = await api("/api/products");
    filterProducts();
  } catch (err) {
    console.error("Error cargando productos:", err);
  }
}

function filterProducts() {
  const search = document.getElementById("productSearch")?.value?.toLowerCase() || "";
  const rows = allProducts.filter(p => 
    p.name.toLowerCase().includes(search) ||
    p.code.toLowerCase().includes(search) ||
    (p.category || "").toLowerCase().includes(search)
  );
  
  document.getElementById("productsTable").innerHTML = table(
    ["ID", "Código", "Nombre", "Categoría", "Stock", "Estado", "Costo", "Venta", "Proveedor", "Acciones"],
    rows.map(r => [
      r.id,
      `<code>${r.code}</code>`,
      `<strong>${r.name}</strong>`,
      r.category || "-",
      `<strong>${r.stock}</strong>`,
      r.stock > 0 
        ? `<span class="badge badge-success">Disponible</span>` 
        : `<span class="badge badge-danger">Agotado</span>`,
      money(r.total_real_cost),
      `<strong style="color:var(--primary)">${money(r.sale_price)}</strong>`,
      r.supplier || "-",
      `<div class="flex gap-2">
        ${canEdit('inventario') ? `<button class="action-btn edit" onclick="editProduct(${r.id})">✏️</button>` : ''}
        ${canDelete('inventario') ? `<button class="action-btn delete" onclick="deleteProduct(${r.id})">🗑️</button>` : ''}
      </div>`
    ])
  );
  
  const outOfStock = allProducts.filter(r => Number(r.stock) <= 0);
  const alert = document.getElementById("stockAlert");
  if (outOfStock.length > 0) {
    alert.style.display = "block";
    alert.textContent = `⚠️ Atención: ${outOfStock.length} producto(s) agotado(s)`;
  } else {
    alert.style.display = "none";
  }
  
  const opts = allProducts.map(r => `<option value="${r.id}" data-sale="${r.sale_price}">${r.name} (Stock: ${r.stock})</option>`).join("");
  document.getElementById("purchaseProduct").innerHTML = `<option value="">Seleccionar producto...</option>${opts}`;
  document.getElementById("saleProduct").innerHTML = `<option value="">Seleccionar producto...</option>${opts}`;
}

async function editProduct(id) {
  try {
    const product = await api(`/api/products/${id}`);
    document.getElementById("editProductId").value = product.id;
    document.getElementById("editCode").value = product.code || "";
    document.getElementById("editName").value = product.name || "";
    document.getElementById("editCategory").value = product.category || "";
    document.getElementById("editSupplier").value = product.supplier || "";
    document.getElementById("editEntryDate").value = product.entry_date || "";
    document.getElementById("editStock").value = product.stock || 0;
    document.getElementById("editProdPurchasePrice").value = product.purchase_price || 0;
    document.getElementById("editProdExtraCosts").value = product.extra_costs || 0;
    document.getElementById("editProdMarginPercent").value = product.margin_percent || 30;
    document.getElementById("editDescription").value = product.description || "";
    document.getElementById("editFeatures").value = product.features || "";
    
    const photoDiv = document.getElementById("editCurrentPhoto");
    if (product.photo_path) {
      photoDiv.innerHTML = `<p class="text-sm text-muted">Foto actual: <a href="${product.photo_path}" target="_blank">Ver imagen</a></p>`;
    } else {
      photoDiv.innerHTML = "";
    }
    
    document.getElementById("editProductModal").classList.add("show");
  } catch (err) {
    console.error("Error loading product:", err);
    showToast("Error al cargar producto", "error");
  }
}

async function deleteProduct(id) {
  if (!confirm("¿Eliminar este producto?")) return;
  try {
    await api(`/api/products/${id}`, { method: "DELETE" });
    showToast("Producto eliminado");
    await refreshProducts();
  } catch (err) {
    showToast(err.message, "error");
  }
}

function closeEditProductModal() {
  document.getElementById("editProductModal").classList.remove("show");
  document.getElementById("editProductForm").reset();
}

// ==================== COMPRAS ====================
async function refreshPurchases() {
  try {
    allPurchases = await api("/api/purchases");
    filterPurchases();
  } catch (err) {
    console.error("Error cargando compras:", err);
  }
}

function filterPurchases() {
  const search = document.getElementById("purchaseSearch")?.value?.toLowerCase() || "";
  const rows = allPurchases.filter(p => 
    p.product_name.toLowerCase().includes(search) ||
    p.supplier?.toLowerCase().includes(search)
  );
  
  document.getElementById("purchasesTable").innerHTML = table(
    ["ID", "Fecha", "Producto", "Cantidad", "Precio", "Envío", "Total", "Proveedor", "Acciones"],
    rows.map(r => [
      r.id, r.purchase_date, r.product_name, r.quantity, 
      money(r.purchase_price), money(r.shipping_cost), 
      money(r.total_invested), r.supplier || "-",
      `<div class="flex gap-2">
        ${canEdit('compras') ? `<button class="action-btn edit" onclick="editPurchase(${r.id})">✏️</button>` : ''}
        ${canDelete('compras') ? `<button class="action-btn delete" onclick="deletePurchase(${r.id})">🗑️</button>` : ''}
      </div>`
    ])
  );
}

async function editPurchase(id) {
  try {
    const purchase = await api(`/api/purchases/${id}`);
    document.getElementById("editPurchaseInfo").innerHTML = `<strong>Producto:</strong> ${purchase.product_name} <span style="color:var(--primary);">•</span> <strong>Stock actual:</strong> <span id="editPurchaseStock">-</span>`;
    document.getElementById("editPurchaseId").value = purchase.id;
    document.getElementById("editPurchaseQty").value = purchase.quantity;
    document.getElementById("editPurchasePrice").value = purchase.purchase_price;
    document.getElementById("editPurchaseSupplier").value = purchase.supplier || "";
    document.getElementById("editPurchaseShip").value = purchase.shipping_cost;
    document.getElementById("editPurchaseDate").value = purchase.purchase_date;
    document.getElementById("editPurchaseTotal").textContent = money(purchase.total_invested);
    
    const product = allProducts.find(p => p.id === purchase.product_id);
    if (product) {
      document.getElementById("editPurchaseStock").textContent = product.stock;
    }
    
    document.getElementById("editPurchaseModal").classList.add("show");
  } catch (err) {
    showToast(err.message, "error");
  }
}

function closeEditPurchaseModal() {
  document.getElementById("editPurchaseModal").classList.remove("show");
}

async function deletePurchase(id) {
  if (!confirm("¿Eliminar esta compra?")) return;
  try {
    await api(`/api/purchases/${id}`, { method: "DELETE" });
    showToast("Compra eliminada");
    await Promise.all([refreshProducts(), refreshPurchases(), refreshCash()]);
  } catch (err) {
    showToast(err.message, "error");
  }
}

// ==================== CLIENTES ====================
async function refreshClients() {
  try {
    allClients = await api("/api/clients");
    filterClients();
  } catch (err) {
    console.error("Error cargando clientes:", err);
  }
}

function filterClients() {
  const search = document.getElementById("clientSearch")?.value?.toLowerCase() || "";
  const rows = allClients.filter(c => 
    c.name.toLowerCase().includes(search) ||
    (c.phone || "").includes(search) ||
    (c.city || "").toLowerCase().includes(search)
  );
  
  document.getElementById("clientsTable").innerHTML = table(
    ["ID", "Nombre", "Teléfono", "Dirección", "Ciudad", "Acciones"],
    rows.map(r => [
      r.id, `<strong>${r.name}</strong>`, r.phone || "-", 
      r.address || "-", r.city || "-",
      `<div class="flex gap-2">
        ${canEdit('clientes') ? `<button class="action-btn edit" onclick="editClient(${r.id})">✏️</button>` : ''}
        ${canDelete('clientes') ? `<button class="action-btn delete" onclick="deleteClient(${r.id})">🗑️</button>` : ''}
      </div>`
    ])
  );
  document.getElementById("saleClient").innerHTML = `<option value="">Cliente (opcional)</option>${allClients.map(r => `<option value="${r.id}">${r.name}</option>`).join("")}`;
}

async function editClient(id) {
  try {
    const client = await api(`/api/clients/${id}`);
    document.getElementById("clientId").value = client.id;
    document.getElementById("clientName").value = client.name;
    document.getElementById("clientPhone").value = client.phone || "";
    document.getElementById("clientAddress").value = client.address || "";
    document.getElementById("clientCity").value = client.city || "";
    document.getElementById("clientBtnText").textContent = "Actualizar Cliente";
    window.scrollTo({ top: 0, behavior: 'smooth' });
  } catch (err) {
    showToast(err.message, "error");
  }
}

async function deleteClient(id) {
  if (!confirm("¿Eliminar este cliente?")) return;
  try {
    await api(`/api/clients/${id}`, { method: "DELETE" });
    showToast("Cliente eliminado");
    await refreshClients();
  } catch (err) {
    showToast(err.message, "error");
  }
}

function resetClientForm() {
  document.getElementById("clientForm").reset();
  document.getElementById("clientId").value = "";
  document.getElementById("clientBtnText").textContent = "Guardar Cliente";
}

// ==================== VENTAS ====================
async function refreshSales() {
  try {
    allSales = await api("/api/sales");
    filterSales();
  } catch (err) {
    console.error("Error cargando ventas:", err);
  }
}

function filterSales() {
  const search = document.getElementById("saleSearch")?.value?.toLowerCase() || "";
  const rows = allSales.filter(s => 
    s.product_name.toLowerCase().includes(search) ||
    (s.client_name || "").toLowerCase().includes(search) ||
    s.payment_method.toLowerCase().includes(search)
  );
  
  document.getElementById("salesTable").innerHTML = table(
    ["ID", "Fecha", "Producto", "Cant.", "Precio", "Total", "Ganancia", "Cliente", "Pago", "Acciones"],
    rows.map(r => [
      r.id, r.sale_date, r.product_name, r.quantity, money(r.sale_price),
      money(r.total_amount),
      `<span style="color:${r.profit >= 0 ? 'var(--success)' : 'var(--danger)'}">${money(r.profit)}</span>`,
      r.client_name || "-", r.payment_method,
      `<div class="flex gap-2">
        ${canEdit('ventas') ? `<button class="action-btn edit" onclick="editSale(${r.id})">✏️</button>` : ''}
        ${canDelete('ventas') ? `<button class="action-btn delete" onclick="deleteSale(${r.id})">🗑️</button>` : ''}
      </div>`
    ])
  );
}

async function editSale(id) {
  try {
    const sale = await api(`/api/sales/${id}`);
    const clientInfo = sale.client_name ? ` <span style="color:var(--primary);">•</span> <strong>Cliente:</strong> ${sale.client_name}` : '';
    document.getElementById("editSaleInfo").innerHTML = `<strong>Producto:</strong> ${sale.product_name}${clientInfo}`;
    document.getElementById("editSaleId").value = sale.id;
    document.getElementById("editSaleDate").value = sale.sale_date;
    document.getElementById("editSaleQty").value = sale.quantity;
    document.getElementById("editSaleQty").dataset.purchasePrice = sale.purchase_price || 0;
    document.getElementById("editSalePrice").value = sale.sale_price;
    document.getElementById("editSalePayment").value = sale.payment_method;
    document.getElementById("editSaleShipCheck").value = sale.includes_shipping ? "1" : "0";
    document.getElementById("editSaleShipValue").value = sale.shipping_value;
    document.getElementById("editSaleTotal").textContent = money(sale.total_amount);
    document.getElementById("editSaleProfit").textContent = money(sale.profit);
    document.getElementById("editSaleModal").classList.add("show");
  } catch (err) {
    showToast(err.message, "error");
  }
}

function closeEditSaleModal() {
  document.getElementById("editSaleModal").classList.remove("show");
}

async function deleteSale(id) {
  if (!confirm("¿Eliminar esta venta?")) return;
  try {
    await api(`/api/sales/${id}`, { method: "DELETE" });
    showToast("Venta eliminada");
    await Promise.all([refreshProducts(), refreshSales(), refreshCash()]);
  } catch (err) {
    showToast(err.message, "error");
  }
}

// ==================== ENVÍOS ====================
async function refreshShipments() {
  try {
    allShipments = await api("/api/shipments");
    filterShipments();
  } catch (err) {
    console.error("Error cargando envíos:", err);
  }
}

function filterShipments() {
  const search = document.getElementById("shipmentSearch")?.value?.toLowerCase() || "";
  const rows = allShipments.filter(s => 
    s.client_name.toLowerCase().includes(search) ||
    (s.city || "").toLowerCase().includes(search) ||
    (s.transport_company || "").toLowerCase().includes(search)
  );
  
  const pending = allShipments.filter(r => r.status === "Pendiente").length;
  const sent = allShipments.filter(r => r.status === "Enviado").length;
  const delivered = allShipments.filter(r => r.status === "Entregado").length;
  document.getElementById("statPending").textContent = pending;
  document.getElementById("statSent").textContent = sent;
  document.getElementById("statDelivered").textContent = delivered;
  
  document.getElementById("shipmentsTable").innerHTML = table(
    ["ID", "Cliente", "Dirección", "Ciudad", "Valor", "Transportadora", "Estado", "Fecha", "Acciones"],
    rows.map(r => [
      r.id, `<strong>${r.client_name}</strong>`, r.client_address, r.city,
      money(r.shipping_value), r.transport_company || "-",
      r.status === "Entregado" ? `<span class="badge badge-success">✅</span>` :
      r.status === "Enviado" ? `<span class="badge badge-info">🚚</span>` :
      `<span class="badge badge-warning">⏳</span>`,
      r.created_at ? new Date(r.created_at).toLocaleDateString("es-CO") : "-",
      `<div class="flex gap-2">
        ${canEdit('envios') ? `<button class="action-btn edit" onclick="editShipment(${r.id})">✏️</button>` : ''}
        ${canEdit('envios') ? `<select class="status-select" data-id="${r.id}" style="width:auto;padding:6px;" onchange="updateShipmentStatus(${r.id}, this.value)">
          <option value="Pendiente" ${r.status === "Pendiente" ? "selected" : ""}>⏳</option>
          <option value="Enviado" ${r.status === "Enviado" ? "selected" : ""}>🚚</option>
          <option value="Entregado" ${r.status === "Entregado" ? "selected" : ""}>✅</option>
        </select>` : ''}
        ${canDelete('envios') ? `<button class="action-btn delete" onclick="deleteShipment(${r.id})">🗑️</button>` : ''}
      </div>`
    ])
  );
}

async function editShipment(id) {
  try {
    const shipment = await api(`/api/shipments/${id}`);
    document.getElementById("shipmentId").value = shipment.id;
    document.getElementById("shipmentForm").sale_id.value = shipment.sale_id || "";
    document.getElementById("shipmentClientName").value = shipment.client_name;
    document.getElementById("shipmentAddress").value = shipment.client_address;
    document.getElementById("shipmentCity").value = shipment.city;
    document.getElementById("shipmentValue").value = shipment.shipping_value;
    document.getElementById("shipmentTransport").value = shipment.transport_company || "";
    document.getElementById("shipmentStatus").value = shipment.status;
    document.getElementById("shipmentBtnText").textContent = "Actualizar Envío";
    document.getElementById("cancelEditBtn").style.display = "inline-flex";
    window.scrollTo({ top: 0, behavior: 'smooth' });
  } catch (err) {
    showToast(err.message, "error");
  }
}

async function updateShipmentStatus(id, status) {
  try {
    await api(`/api/shipments/${id}`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ 
        client_name: "", client_address: "", city: "", status 
      })
    });
    showToast("Estado actualizado");
    await refreshShipments();
  } catch (err) {
    showToast(err.message, "error");
  }
}

async function deleteShipment(id) {
  if (!confirm("¿Eliminar este envío?")) return;
  try {
    await api(`/api/shipments/${id}`, { method: "DELETE" });
    showToast("Envío eliminado");
    await refreshShipments();
  } catch (err) {
    showToast(err.message, "error");
  }
}

function resetShipmentForm() {
  document.getElementById("shipmentForm").reset();
  document.getElementById("shipmentId").value = "";
  document.getElementById("shipmentBtnText").textContent = "Guardar Envío";
  document.getElementById("cancelEditBtn").style.display = "none";
}

function cancelShipmentEdit() {
  resetShipmentForm();
}

// ==================== CAJA ====================
async function refreshCash() {
  try {
    const summary = await api("/api/cash/summary");
    document.getElementById("metricInvestment").textContent = money(summary.initial_investment);
    document.getElementById("metricIncomes").textContent = money(summary.incomes);
    document.getElementById("metricExpenses").textContent = money(summary.expenses);
    document.getElementById("metricCurrent").textContent = money(summary.current);
    await loadCashMovements();
  } catch (err) {
    console.error("Error cargando caja:", err);
  }
}

async function loadCashMovements() {
  try {
    const startDate = document.getElementById("cashStartDate").value;
    const endDate = document.getElementById("cashEndDate").value;
    const type = document.getElementById("cashTypeFilter").value;
    
    let url = "/api/cash-movements?";
    const params = [];
    if (startDate) params.push(`start_date=${startDate}`);
    if (endDate) params.push(`end_date=${endDate}`);
    if (type !== "all") params.push(`type=${type}`);
    url += params.join("&");
    
    allCashMovements = await api(url);
    filterCashMovements();
  } catch (err) {
    console.error("Error cargando movimientos:", err);
  }
}

let cashCurrentPage = 1;
let cashFilteredData = [];

function filterCashMovements() {
  const search = document.getElementById("cashSearch")?.value?.toLowerCase() || "";
  const perPage = parseInt(document.getElementById("cashPerPage")?.value || 25);
  
  cashFilteredData = allCashMovements.filter(m => 
    m.category.toLowerCase().includes(search) ||
    (m.notes || "").toLowerCase().includes(search)
  );
  
  const totalPages = Math.ceil(cashFilteredData.length / perPage);
  const start = (cashCurrentPage - 1) * perPage;
  const end = start + perPage;
  const pageData = cashFilteredData.slice(start, end);
  
  document.getElementById("cashTable").innerHTML = table(
    ["Fecha", "Tipo", "Categoría", "Monto", "Notas", "Acciones"],
    pageData.map(r => [
      r.movement_date,
      r.type === "Ingreso" ? `<span class="badge badge-success">📈</span>` : `<span class="badge badge-danger">📉</span>`,
      r.category, money(r.amount), r.notes || "-",
      canDelete('caja') ? `<button class="action-btn delete" onclick="deleteCashMovement(${r.id})">🗑️</button>` : ''
    ])
  );
  
  const totalIngresos = cashFilteredData.filter(m => m.type === "Ingreso").reduce((sum, m) => sum + Number(m.amount), 0);
  const totalEgresos = cashFilteredData.filter(m => m.type === "Egreso").reduce((sum, m) => sum + Number(m.amount), 0);
  const balance = totalIngresos - totalEgresos;
  
  document.getElementById("cashTotals").innerHTML = `
    <div style="text-align:center;">
      <div style="font-size:0.85rem;color:var(--text-muted);">Total Ingresos</div>
      <div style="font-size:1.5rem;font-weight:700;color:var(--success);">${money(totalIngresos)}</div>
    </div>
    <div style="text-align:center;">
      <div style="font-size:0.85rem;color:var(--text-muted);">Total Egresos</div>
      <div style="font-size:1.5rem;font-weight:700;color:var(--danger);">${money(totalEgresos)}</div>
    </div>
    <div style="text-align:center;">
      <div style="font-size:0.85rem;color:var(--text-muted);">Balance</div>
      <div style="font-size:1.5rem;font-weight:700;color:${balance >= 0 ? 'var(--success)' : 'var(--danger)'};">${money(balance)}</div>
    </div>
  `;
  
  renderPagination(totalPages, cashCurrentPage, "cashPagination", (page) => {
    cashCurrentPage = page;
    filterCashMovements();
  });
}

async function deleteCashMovement(id) {
  if (!confirm("¿Eliminar este movimiento?")) return;
  try {
    await api(`/api/cash-movements/${id}`, { method: "DELETE" });
    showToast("Movimiento eliminado");
    await refreshCash();
  } catch (err) {
    showToast(err.message, "error");
  }
}

// ==================== REPORTES ====================
let reportFilter = { start_date: null, end_date: null };

async function refreshReports() {
  try {
    const s = await api("/api/reports/summary");
    document.getElementById("reportMetrics").innerHTML = `
      <div class="metric-card"><div class="metric-label">Ventas Hoy</div><div class="metric-value">${money(s.today)}</div></div>
      <div class="metric-card success"><div class="metric-label">Ventas Semana</div><div class="metric-value">${money(s.week)}</div></div>
      <div class="metric-card"><div class="metric-label">Ventas Mes</div><div class="metric-value">${money(s.month)}</div></div>
      <div class="metric-card success"><div class="metric-label">Ganancia Total</div><div class="metric-value">${money(s.totalProfit)}</div></div>
      <div class="metric-card"><div class="metric-label">Ingresos</div><div class="metric-value">${money(s.income)}</div></div>
      <div class="metric-card danger"><div class="metric-label">Gastos</div><div class="metric-value">${money(s.expense)}</div></div>
    `;
    
    document.getElementById("outOfStock").innerHTML = s.outOfStock.length
      ? table(["Código", "Producto", "Stock"], s.outOfStock.map(p => [p.code, p.name, p.stock]))
      : `<div class="empty-state"><div class="icon">🎉</div><p>¡Sin productos agotados!</p></div>`;

    const c = await api("/api/reports/charts");
    const labels = c.salesByMonth.map(x => x.month);
    const sales = c.salesByMonth.map(x => x.total_sales);
    const profits = c.salesByMonth.map(x => x.total_profit);
    const topLabels = c.topProducts.map(x => x.name);
    const topQty = c.topProducts.map(x => x.qty);
    
    if (salesChart) salesChart.destroy();
    if (profitChart) profitChart.destroy();
    if (topProductsChart) topProductsChart.destroy();
    if (unifiedChart) unifiedChart.destroy();
    
    const chartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: true, position: "top" } } };
    
    salesChart = new Chart(document.getElementById("salesChart"), {
      type: "line",
      data: { labels, datasets: [{ label: "Ventas", data: sales, borderColor: "#e91e8c", backgroundColor: "rgba(233, 30, 140, 0.1)", fill: true, tension: 0.4 }] },
      options: chartOptions
    });
    
    profitChart = new Chart(document.getElementById("profitChart"), {
      type: "bar",
      data: { labels, datasets: [{ label: "Ganancias", data: profits, backgroundColor: "#7c3aed" }] },
      options: chartOptions
    });
    
    topProductsChart = new Chart(document.getElementById("topProductsChart"), {
      type: "bar",
      data: { labels: topLabels, datasets: [{ label: "Cantidad Vendida", data: topQty, backgroundColor: "#06b6d4" }] },
      options: { ...chartOptions, indexAxis: "y" }
    });
    
    await loadUnifiedChart();
  } catch (err) {
    console.error("Error cargando reportes:", err);
  }
}

async function loadUnifiedChart() {
  try {
    const startDate = document.getElementById("reportStartDate").value;
    const endDate = document.getElementById("reportEndDate").value;
    
    let url = "/api/reports/filtered";
    const params = [];
    if (startDate) params.push(`start_date=${startDate}`);
    if (endDate) params.push(`end_date=${endDate}`);
    if (params.length > 0) url += "?" + params.join("&");
    
    const data = await api(url);
    
    const labels = data.salesByDay.map(x => x.day);
    const salesData = data.salesByDay.map(x => x.total_sales);
    const profitData = data.salesByDay.map(x => x.total_profit);
    const qtyData = data.salesByDay.map(x => x.total_qty);
    
    if (unifiedChart) unifiedChart.destroy();
    
    unifiedChart = new Chart(document.getElementById("unifiedChart"), {
      type: "bar",
      data: {
        labels,
        datasets: [
          { label: "Ventas ($)", data: salesData, backgroundColor: "rgba(233, 30, 140, 0.8)", yAxisID: "y" },
          { label: "Ganancias ($)", data: profitData, backgroundColor: "rgba(124, 58, 237, 0.8)", yAxisID: "y" },
          { label: "Cantidad", data: qtyData, type: "line", borderColor: "#06b6d4", backgroundColor: "transparent", yAxisID: "y1", tension: 0.4 }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: "index", intersect: false },
        plugins: {
          legend: { display: true, position: "top" },
          tooltip: { callbacks: { label: ctx => `${ctx.dataset.label}: ${money(ctx.raw)}` } }
        },
        scales: {
          y: { type: "linear", display: true, position: "left", title: { display: true, text: "Pesos ($)" } },
          y1: { type: "linear", display: true, position: "right", title: { display: true, text: "Cantidad" }, grid: { drawOnChartArea: false } }
        }
      }
    });
  } catch (err) {
    console.error("Error cargando gráfico unificado:", err);
  }
}

function applyReportFilter() {
  const startDate = document.getElementById("reportStartDate").value;
  const endDate = document.getElementById("reportEndDate").value;
  if (!startDate && !endDate) { showToast("Selecciona al menos una fecha", "info"); return; }
  loadUnifiedChart();
}

function clearReportFilter() {
  document.getElementById("reportStartDate").value = "";
  document.getElementById("reportEndDate").value = "";
  loadUnifiedChart();
}

// ==================== BACKUPS ====================
async function refreshBackups() {
  try {
    const rows = await api("/api/backups");
    document.getElementById("backupTable").innerHTML = rows.length
      ? table(["Archivo", "Fecha", "Tamaño", "Acción"], rows.map(r => [
          r.file, new Date(r.updated_at).toLocaleString("es-CO"),
          `${Math.round(r.size / 1024)} KB`,
          `<a href="${r.url}" target="_blank" class="action-btn view">Descargar</a>`
        ]))
      : `<div class="empty-state"><div class="icon">📦</div><p>Sin respaldos aún</p></div>`;
  } catch (err) {
    console.error("Error cargando backups:", err);
  }
}

// ==================== ADMIN ====================
let allUsers = [];
let allRoles = [];
let currentPermissionsUserId = null;

async function loadAdminData() {
  try {
    const [users, roles] = await Promise.all([
      api("/api/admin/users"),
      api("/api/admin/roles")
    ]);
    allUsers = users;
    allRoles = roles;
    renderUsers();
    populateRoleSelects();
  } catch (err) {
    console.error("Error cargando datos de admin:", err);
  }
}

function renderUsers() {
  const rows = allUsers.map(u => [
    u.id,
    `<strong>${u.username}</strong>`,
    u.role_name || "Sin rol",
    u.is_active ? `<span class="badge badge-success">Activo</span>` : `<span class="badge badge-danger">Inactivo</span>`,
    new Date(u.created_at).toLocaleDateString("es-CO"),
    `<div class="flex gap-2">
      <button class="action-btn edit" onclick="editUser(${u.id})">✏️</button>
      <button class="action-btn" onclick="managePermissions(${u.id}, '${u.username}')" title="Permisos">🔐</button>
      <button class="action-btn delete" onclick="deleteUser(${u.id})">🗑️</button>
    </div>`
  ]);
  
  document.getElementById("usersTable").innerHTML = table(
    ["ID", "Usuario", "Rol", "Estado", "Creado", "Acciones"],
    rows
  );
}

function populateRoleSelects() {
  const options = allRoles.map(r => `<option value="${r.id}">${r.name}</option>`).join("");
  document.getElementById("newUserRole").innerHTML = `<option value="">Seleccionar rol...</option>${options}`;
  document.getElementById("editUserRole").innerHTML = options;
}

async function editUser(id) {
  const user = allUsers.find(u => u.id === id);
  if (!user) return;
  
  document.getElementById("editUserId").value = user.id;
  document.getElementById("editUserName").value = user.username;
  document.getElementById("editUserPassword").value = "";
  document.getElementById("editUserRole").value = user.role_id;
  document.getElementById("editUserActive").value = user.is_active ? "1" : "0";
  document.getElementById("editUserModal").classList.add("show");
}

function closeEditUserModal() {
  document.getElementById("editUserModal").classList.remove("show");
}

async function deleteUser(id) {
  if (!confirm("¿Eliminar este usuario?")) return;
  try {
    await api(`/api/admin/users/${id}`, { method: "DELETE" });
    showToast("Usuario eliminado");
    await loadAdminData();
  } catch (err) {
    showToast(err.message, "error");
  }
}

async function managePermissions(userId, username) {
  try {
    currentPermissionsUserId = userId;
    document.getElementById("permissionsUserName").innerHTML = `Editando permisos para: <strong>${username}</strong>`;
    
    const permissions = await api(`/api/admin/permissions/${userId}`);
    
    let html = '<table style="width:100%;border-collapse:collapse;">';
    html += '<tr style="background:var(--primary-light);"><th style="padding:10px;text-align:left;">Módulo</th><th style="padding:10px;">Ver</th><th style="padding:10px;">Crear</th><th style="padding:10px;">Editar</th><th style="padding:10px;">Eliminar</th></tr>';
    
    for (const mod of permissions) {
      html += `
        <tr style="border-bottom:1px solid var(--border);">
          <td style="padding:12px;">
            <span style="font-size:1.1em;">${mod.icon || "📄"}</span>
            <strong>${mod.name}</strong>
          </td>
          <td style="padding:8px;text-align:center;">
            <input type="checkbox" id="perm_view_${mod.key}" value="1" ${mod.can_view ? 'checked' : ''} style="transform:scale(1.3);cursor:pointer;">
          </td>
          <td style="padding:8px;text-align:center;">
            <input type="checkbox" id="perm_create_${mod.key}" value="1" ${mod.can_create ? 'checked' : ''} style="transform:scale(1.3);cursor:pointer;">
          </td>
          <td style="padding:8px;text-align:center;">
            <input type="checkbox" id="perm_edit_${mod.key}" value="1" ${mod.can_edit ? 'checked' : ''} style="transform:scale(1.3);cursor:pointer;">
          </td>
          <td style="padding:8px;text-align:center;">
            <input type="checkbox" id="perm_delete_${mod.key}" value="1" ${mod.can_delete ? 'checked' : ''} style="transform:scale(1.3);cursor:pointer;">
          </td>
        </tr>
      `;
    }
    html += '</table>';
    
    document.getElementById("permissionsList").innerHTML = html;
    document.getElementById("permissionsModal").classList.add("show");
  } catch (err) {
    showToast(err.message, "error");
  }
}

function closePermissionsModal() {
  document.getElementById("permissionsModal").classList.remove("show");
  currentPermissionsUserId = null;
}

async function savePermissions() {
  if (!currentPermissionsUserId) return;
  
  const permissions = [];
  const modules = ["clientes", "inventario", "compras", "ventas", "envios", "caja", "reportes"];
  
  for (const mod of modules) {
    permissions.push({
      module_key: mod,
      can_view: document.getElementById(`perm_view_${mod}`)?.checked || false,
      can_create: document.getElementById(`perm_create_${mod}`)?.checked || false,
      can_edit: document.getElementById(`perm_edit_${mod}`)?.checked || false,
      can_delete: document.getElementById(`perm_delete_${mod}`)?.checked || false
    });
  }
  
  try {
    await api(`/api/admin/permissions/${currentPermissionsUserId}`, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ permissions })
    });
    showToast("Permisos actualizados");
    closePermissionsModal();
  } catch (err) {
    showToast(err.message, "error");
  }
}

// ==================== LIVE CALCULATIONS ====================
function calcEditPurchaseTotal() {
  const qty = parseFloat(document.getElementById("editPurchaseQty").value) || 0;
  const price = parseFloat(document.getElementById("editPurchasePrice").value) || 0;
  const ship = parseFloat(document.getElementById("editPurchaseShip").value) || 0;
  const total = (qty * price) + ship;
  document.getElementById("editPurchaseTotal").textContent = money(total);
}

function calcEditSaleTotals() {
  const qty = parseFloat(document.getElementById("editSaleQty").value) || 0;
  const price = parseFloat(document.getElementById("editSalePrice").value) || 0;
  const shipCheck = document.getElementById("editSaleShipCheck").value === "1";
  const shipValue = parseFloat(document.getElementById("editSaleShipValue").value) || 0;
  const total = (qty * price) + (shipCheck ? shipValue : 0);
  const purchasePrice = parseFloat(document.getElementById("editSaleQty").dataset.purchasePrice) || 0;
  const profit = (price - purchasePrice) * qty;
  document.getElementById("editSaleTotal").textContent = money(total);
  document.getElementById("editSaleProfit").textContent = money(profit);
}

// ==================== BIND FORMS ====================
function bindForms() {
  document.getElementById("productForm").entry_date.value = today();
  document.getElementById("purchaseForm").purchase_date.value = today();
  document.getElementById("saleForm").sale_date.value = today();
  document.getElementById("cashForm").movement_date.value = today();

  // Live calculation for edit purchase form
  document.getElementById("editPurchaseQty").addEventListener("input", calcEditPurchaseTotal);
  document.getElementById("editPurchasePrice").addEventListener("input", calcEditPurchaseTotal);
  document.getElementById("editPurchaseShip").addEventListener("input", calcEditPurchaseTotal);

  // Live calculation for edit sale form
  document.getElementById("editSaleQty").addEventListener("input", calcEditSaleTotals);
  document.getElementById("editSalePrice").addEventListener("input", calcEditSaleTotals);
  document.getElementById("editSaleShipCheck").addEventListener("change", calcEditSaleTotals);
  document.getElementById("editSaleShipValue").addEventListener("input", calcEditSaleTotals);

  // Producto Form
  document.getElementById("productForm").addEventListener("submit", async e => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const id = formData.get("id");
    try {
      if (id && parseInt(id) > 0) {
        await api(`/api/products/${id}`, { method: "PUT", credentials: "include", body: formData });
        showToast("Producto actualizado");
      } else {
        await api("/api/products", { method: "POST", credentials: "include", body: formData });
        showToast("Producto guardado");
      }
      e.target.reset();
      e.target.entry_date.value = today();
      await refreshProducts();
    } catch (err) {
      showToast(err.message, "error");
    }
  });

  // Edit Product Form
  document.getElementById("editProductForm").addEventListener("submit", async e => {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    try {
      await api(`/api/products/${document.getElementById("editProductId").value}`, {
        method: "PUT", headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
      });
      closeEditProductModal();
      await refreshProducts();
      showToast("Producto actualizado");
    } catch (err) {
      showToast(err.message, "error");
    }
  });

  // Edit Purchase Form
  document.getElementById("editPurchaseForm").addEventListener("submit", async e => {
    e.preventDefault();
    try {
      await api(`/api/purchases/${document.getElementById("editPurchaseId").value}`, {
        method: "PUT", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          quantity: e.target.quantity.value,
          purchase_price: e.target.purchase_price.value,
          supplier: e.target.supplier.value,
          shipping_cost: e.target.shipping_cost.value,
          purchase_date: e.target.purchase_date.value
        })
      });
      closeEditPurchaseModal();
      await Promise.all([refreshProducts(), refreshPurchases(), refreshCash()]);
      showToast("Compra actualizada");
    } catch (err) {
      showToast(err.message, "error");
    }
  });

  // Edit Sale Form
  document.getElementById("editSaleForm").addEventListener("submit", async e => {
    e.preventDefault();
    try {
      await api(`/api/sales/${document.getElementById("editSaleId").value}`, {
        method: "PUT", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          sale_date: e.target.sale_date.value,
          quantity: e.target.quantity.value,
          sale_price: e.target.sale_price.value,
          payment_method: e.target.payment_method.value,
          includes_shipping: e.target.includes_shipping.value,
          shipping_value: e.target.shipping_value.value
        })
      });
      closeEditSaleModal();
      await Promise.all([refreshProducts(), refreshSales(), refreshCash()]);
      showToast("Venta actualizada");
    } catch (err) {
      showToast(err.message, "error");
    }
  });

  // Compras
  document.getElementById("purchaseForm").addEventListener("submit", async e => {
    e.preventDefault();
    try {
      await api("/api/purchases", {
        method: "POST", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          product_id: e.target.product_id.value,
          quantity: e.target.quantity.value,
          purchase_price: e.target.purchase_price.value,
          supplier: e.target.supplier.value,
          shipping_cost: e.target.shipping_cost.value,
          purchase_date: e.target.purchase_date.value
        })
      });
      e.target.reset();
      e.target.purchase_date.value = today();
      await Promise.all([refreshProducts(), refreshPurchases(), refreshCash()]);
      showToast("Compra registrada");
    } catch (err) {
      showToast(err.message, "error");
    }
  });

  // Ventas
  document.getElementById("saleForm").addEventListener("submit", async e => {
    e.preventDefault();
    try {
      await api("/api/sales", {
        method: "POST", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          sale_date: e.target.sale_date.value,
          product_id: e.target.product_id.value,
          quantity: e.target.quantity.value,
          sale_price: e.target.sale_price.value,
          client_id: e.target.client_id.value || null,
          payment_method: e.target.payment_method.value,
          includes_shipping: e.target.includes_shipping.value,
          shipping_value: e.target.shipping_value.value
        })
      });
      e.target.reset();
      e.target.sale_date.value = today();
      await Promise.all([refreshProducts(), refreshSales(), refreshCash()]);
      showToast("Venta registrada");
    } catch (err) {
      showToast(err.message, "error");
    }
  });

  // Envíos
  document.getElementById("shipmentForm").addEventListener("submit", async e => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const id = formData.get("id");
    const clientName = formData.get("client_name");
    const clientAddress = formData.get("client_address");
    const city = formData.get("city");
    
    if (!clientName || !clientAddress || !city) {
      showToast("Por favor complete los campos obligatorios", "error");
      return;
    }
    
    const payload = {
      client_name: clientName,
      client_address: clientAddress,
      city: city,
      shipping_value: parseFloat(formData.get("shipping_value")) || 0,
      transport_company: formData.get("transport_company") || "",
      status: formData.get("status") || "Pendiente"
    };
    
    const saleIdValue = formData.get("sale_id");
    if (saleIdValue && saleIdValue.trim() !== "") {
      payload.sale_id = parseInt(saleIdValue);
    }
    
    try {
      if (id && parseInt(id) > 0) {
        await api(`/api/shipments/${id}`, { 
          method: "PUT", 
          headers: { "Content-Type": "application/json" }, 
          body: JSON.stringify(payload) 
        });
        showToast("Envío actualizado");
      } else {
        await api("/api/shipments", { 
          method: "POST", 
          headers: { "Content-Type": "application/json" }, 
          body: JSON.stringify(payload) 
        });
        showToast("Envío registrado");
      }
      resetShipmentForm();
      await refreshShipments();
    } catch (err) {
      console.error("Error saving shipment:", err);
    }
  });

  // Clientes
  document.getElementById("clientForm").addEventListener("submit", async e => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const id = formData.get("id");
    const payload = {
      name: formData.get("name"),
      phone: formData.get("phone") || "",
      address: formData.get("address") || "",
      city: formData.get("city") || ""
    };
    try {
      if (id && parseInt(id) > 0) {
        await api(`/api/clients/${id}`, { method: "PUT", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) });
        showToast("Cliente actualizado");
      } else {
        await api("/api/clients", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) });
        showToast("Cliente guardado");
      }
      e.target.reset();
      document.getElementById("clientId").value = "";
      document.getElementById("clientBtnText").textContent = "Guardar Cliente";
      await refreshClients();
    } catch (err) {
      showToast(err.message, "error");
    }
  });

  // Caja
  document.getElementById("cashForm").addEventListener("submit", async e => {
    e.preventDefault();
    try {
      await api("/api/cash-movements", {
        method: "POST", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          movement_date: e.target.movement_date.value,
          type: e.target.type.value,
          category: e.target.category.value,
          amount: e.target.amount.value,
          notes: e.target.notes.value
        })
      });
      e.target.reset();
      e.target.movement_date.value = today();
      await refreshCash();
      showToast("Movimiento registrado");
    } catch (err) {
      showToast(err.message, "error");
    }
  });

  // Configuración
  document.getElementById("settingsForm").addEventListener("submit", async e => {
    e.preventDefault();
    try {
      await api("/api/settings", {
        method: "PUT", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          default_margin_percent: e.target.default_margin_percent.value,
          initial_investment: e.target.initial_investment.value
        })
      });
      await refreshCash();
      showToast("Configuración guardada");
    } catch (err) {
      showToast(err.message, "error");
    }
  });

  // Admin - Crear Usuario
  document.getElementById("createUserForm").addEventListener("submit", async e => {
    e.preventDefault();
    try {
      await api("/api/admin/users", {
        method: "POST", headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          username: document.getElementById("newUserName").value,
          password: document.getElementById("newUserPassword").value,
          role_id: document.getElementById("newUserRole").value
        })
      });
      e.target.reset();
      await loadAdminData();
      showToast("Usuario creado");
    } catch (err) {
      showToast(err.message, "error");
    }
  });

  // Admin - Editar Usuario
  document.getElementById("editUserForm").addEventListener("submit", async e => {
    e.preventDefault();
    const userId = document.getElementById("editUserId").value;
    const payload = {
      username: document.getElementById("editUserName").value,
      role_id: document.getElementById("editUserRole").value,
      is_active: document.getElementById("editUserActive").value === "1"
    };
    const password = document.getElementById("editUserPassword").value;
    if (password) payload.password = password;
    
    try {
      await api(`/api/admin/users/${userId}`, {
        method: "PUT", headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      });
      closeEditUserModal();
      await loadAdminData();
      showToast("Usuario actualizado");
    } catch (err) {
      showToast(err.message, "error");
    }
  });

  // Selectores
  document.getElementById("saleProduct").addEventListener("change", e => {
    const opt = e.target.selectedOptions[0];
    if (opt && opt.dataset.sale) {
      document.getElementById("saleForm").sale_price.value = opt.dataset.sale;
    }
  });

  document.getElementById("backupNowBtn").addEventListener("click", async () => {
    try {
      await api("/api/backups", { method: "POST" });
      await refreshBackups();
      showToast("Respaldo creado");
    } catch (err) {
      showToast(err.message, "error");
    }
  });

  document.getElementById("logoutBtn").addEventListener("click", async () => {
    await api("/api/auth/logout", { method: "POST" });
    location.reload();
  });

  // Filtros reactivos
  document.getElementById("productSearch")?.addEventListener("input", filterProducts);
  document.getElementById("purchaseSearch")?.addEventListener("input", filterPurchases);
  document.getElementById("saleSearch")?.addEventListener("input", filterSales);
  document.getElementById("clientSearch")?.addEventListener("input", filterClients);
  document.getElementById("shipmentSearch")?.addEventListener("input", filterShipments);
  document.getElementById("cashSearch")?.addEventListener("input", () => { cashCurrentPage = 1; filterCashMovements(); });
  
  document.getElementById("cashStartDate")?.addEventListener("change", () => { cashCurrentPage = 1; loadCashMovements(); });
  document.getElementById("cashEndDate")?.addEventListener("change", () => { cashCurrentPage = 1; loadCashMovements(); });
  document.getElementById("cashTypeFilter")?.addEventListener("change", () => { cashCurrentPage = 1; loadCashMovements(); });
  document.getElementById("cashPerPage")?.addEventListener("change", () => { cashCurrentPage = 1; filterCashMovements(); });
}

async function loadSettings() {
  try {
    const s = await api("/api/settings");
    const f = document.getElementById("settingsForm");
    f.default_margin_percent.value = s.default_margin_percent;
    f.initial_investment.value = s.initial_investment;
  } catch (err) {
    console.error("Error cargando configuración:", err);
  }
}

async function loadAll() {
  await Promise.all([
    refreshProducts(), refreshPurchases(), refreshClients(),
    refreshSales(), refreshShipments(), refreshCash(), refreshBackups(), loadSettings()
  ]);
  updateButtonPermissions();
}

function updateButtonPermissions() {
  // Productos
  const addProductBtn = document.getElementById("addProductBtn");
  if (addProductBtn) addProductBtn.style.display = canCreate('inventario') ? '' : 'none';
  
  // Clientes
  const addClientBtn = document.getElementById("addClientBtn");
  if (addClientBtn) addClientBtn.style.display = canCreate('clientes') ? '' : 'none';
  
  // Compras
  const addPurchaseBtn = document.getElementById("addPurchaseBtn");
  if (addPurchaseBtn) addPurchaseBtn.style.display = canCreate('compras') ? '' : 'none';
  
  // Ventas
  const addSaleBtn = document.getElementById("addSaleBtn");
  if (addSaleBtn) addSaleBtn.style.display = canCreate('ventas') ? '' : 'none';
  
  // Envíos
  const addShipmentBtn = document.getElementById("addShipmentBtn");
  if (addShipmentBtn) addShipmentBtn.style.display = canCreate('envios') ? '' : 'none';
  
  // Caja
  const addCashBtn = document.getElementById("addCashBtn");
  if (addCashBtn) addCashBtn.style.display = canCreate('caja') ? '' : 'none';
}

function exportData(type) {
  window.open(`${BASE_PATH}index.php?_route=export/${type}`, "_blank");
  showToast(`Exportando ${type}...`);
}

function downloadTemplate(type) {
  window.open(`${BASE_PATH}index.php?_route=template/${type}`, "_blank");
  showToast(`Descargando plantilla ${type}...`);
}

async function exportToExcel(type) {
  try {
    showToast(`Exportando ${type} a Excel...`);
    const data = await api(`/api/${type}`);
    const ws = XLSX.utils.json_to_sheet(data);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, type);
    XLSX.writeFile(wb, `${type}_${new Date().toISOString().slice(0,10)}.xlsx`);
    showToast(`Excel ${type} descargado`);
  } catch (err) {
    showToast("Error al exportar: " + err.message, "error");
  }
}

function parseCsv(text) {
  const lines = text.split("\n").filter(l => l.trim());
  if (lines.length < 2) return [];
  const headers = lines[0].split(",").map(h => h.trim().replace(/^"|"$/g, "").replace(/""/g, '"'));
  return lines.slice(1).map(line => {
    const values = [];
    let current = "";
    let inQuotes = false;
    for (let i = 0; i < line.length; i++) {
      const c = line[i];
      if (c === '"') {
        if (inQuotes && line[i+1] === '"') { current += '"'; i++; }
        else inQuotes = !inQuotes;
      } else if (c === "," && !inQuotes) {
        values.push(current.trim());
        current = "";
      } else {
        current += c;
      }
    }
    values.push(current.trim());
    const obj = {};
    headers.forEach((h, i) => obj[h] = values[i] || "");
    return obj;
  });
}

async function importData() {
  const type = document.getElementById("importType").value;
  const fileInput = document.getElementById("importFile");
  const file = fileInput.files[0];
  
  if (!file) { showToast("Selecciona un archivo", "error"); return; }
  
  try {
    let data = [];
    const fileName = file.name.toLowerCase();
    
    if (fileName.endsWith('.xlsx') || fileName.endsWith('.xls')) {
      const arrayBuffer = await file.arrayBuffer();
      const workbook = XLSX.read(new Uint8Array(arrayBuffer), { type: 'array' });
      const sheetName = workbook.SheetNames[0];
      const worksheet = workbook.Sheets[sheetName];
      data = XLSX.utils.sheet_to_json(worksheet);
    } else if (fileName.endsWith('.json')) {
      const text = await file.text();
      const json = JSON.parse(text);
      if (Array.isArray(json)) {
        data = json;
      } else if (json.data && Array.isArray(json.data)) {
        data = json.data;
      }
    } else {
      const text = await file.text();
      data = parseCsv(text);
    }
    
    if (data.length === 0) { showToast("Archivo vacío o formato inválido", "error"); return; }
    
    const result = await api(`/api/import/${type}`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ data })
    });
    
    let msg = `Importados: ${result.imported} de ${result.total} registros`;
    if (result.errors && result.errors.length > 0) {
      msg += ` (${result.errors.length} errores)`;
    }
    showToast(msg);
    fileInput.value = "";
    
    if (type === "products") refreshProducts();
    else if (type === "clients") refreshClients();
    else if (type === "sales") refreshSales();
    else if (type === "purchases") refreshPurchases();
  } catch (err) {
    showToast("Error al importar: " + err.message, "error");
  }
}

async function refreshBackups() {
  try {
    const backups = await api("/api/backups");
    const container = document.getElementById("backupsList");
    if (!backups || backups.length === 0) {
      container.innerHTML = '<p style="color:#92400e;">No hay respaldos disponibles</p>';
      return;
    }
    container.innerHTML = backups.map(b => `
      <div style="display:flex;justify-content:space-between;align-items:center;padding:8px;background:white;border-radius:8px;margin-bottom:4px;">
        <span style="font-size:0.85rem;">${b.file} (${(b.size/1024).toFixed(1)} KB)</span>
        <div style="display:flex;gap:8px;">
          <button onclick="downloadBackup('${b.file}')" class="btn btn-sm" style="background:#3b82f6;color:white;padding:4px 8px;">⬇️</button>
        </div>
      </div>
    `).join('');
  } catch (err) {
    showToast("Error cargando respaldos: " + err.message, "error");
  }
}

async function createBackup() {
  try {
    showToast("Creando respaldo...");
    const result = await api("/api/backups", { method: "POST" });
    showToast("Respaldo creado: " + result.file);
    await refreshBackups();
  } catch (err) {
    showToast("Error al crear respaldo: " + err.message, "error");
  }
}

function downloadBackup(filename) {
  window.open(`${BASE_PATH}index.php?_route=backups/download&file=${encodeURIComponent(filename)}`, "_blank");
}

async function initAuth() {
  const overlay = document.getElementById("authOverlay");
  const bootstrapForm = document.getElementById("bootstrapForm");
  const loginForm = document.getElementById("loginForm");
  const msg = document.getElementById("authMessage");

  try {
    const status = await api("/api/auth/status");
    if (status.authenticated) {
      overlay.classList.remove("show");
      document.getElementById("whoami").textContent = `👤 ${status.username} (${status.role})`;
      
      // Aplicar permisos
      applyPermissions(status.permissions, status.isAdmin);
      
      if (status.isAdmin) {
        document.getElementById("adminTab").style.display = "";
        await loadAdminData();
      }
      
      return true;
    }

    overlay.classList.add("show");
    if (!status.hasUsers) {
      msg.textContent = "Configura tu cuenta de administrador para comenzar";
      bootstrapForm.style.display = "block";
      loginForm.style.display = "none";
    } else {
      msg.textContent = "Ingresa con tus credenciales";
      bootstrapForm.style.display = "none";
      loginForm.style.display = "block";
    }

    bootstrapForm.addEventListener("submit", async e => {
      e.preventDefault();
      try {
        await api("/api/auth/bootstrap", {
          method: "POST", headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ username: e.target.username.value, password: e.target.password.value })
        });
        showToast("Usuario creado. Ahora inicia sesión");
        bootstrapForm.style.display = "none";
        loginForm.style.display = "block";
        msg.textContent = "Ingresa con tu nuevo usuario";
      } catch (err) {
        showToast(err.message, "error");
      }
    });

    loginForm.addEventListener("submit", async e => {
      e.preventDefault();
      try {
        await api("/api/auth/login", {
          method: "POST", headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ username: e.target.username.value, password: e.target.password.value })
        });
        location.reload();
      } catch (err) {
        showToast(err.message, "error");
      }
    });
  } catch (err) {
    showToast("Error de conexión", "error");
  }

  return false;
}

(async function start() {
  setupTabs();
  bindForms();
  const ok = await initAuth();
  if (ok) await loadAll();
})();
