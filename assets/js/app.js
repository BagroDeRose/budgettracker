/**
 * Основная логика приложения BudgetTracker
 */

// Глобальные переменные
let expenseChart = null;
let allCategories = [];
let transToDeleteId = null;

// === ФУНКЦИИ МОДАЛЬНОГО ОКНА УДАЛЕНИЯ ===

window.openTransDeleteModal = function(id) {
    transToDeleteId = id;
    document.getElementById('delete-trans-modal').showModal();
};

window.closeDeleteTransModal = function() {
    transToDeleteId = null;
    document.getElementById('delete-trans-modal').close();
};

window.confirmTransDelete = function() {
    if (transToDeleteId) {
        executeDelete(transToDeleteId);
        transToDeleteId = null;
    }
    closeDeleteTransModal();
};

// === ГЛОБАЛЬНЫЕ ФУНКЦИИ ===

window.loadTransactions = async function() {
    const params = {
        action: 'get_list',
        date_from: document.getElementById('filter-date-from')?.value || null,
        date_to: document.getElementById('filter-date-to')?.value || null,
        type: document.getElementById('filter-type')?.value || null,
        category_id: document.getElementById('filter-category')?.value || null,
        search: document.getElementById('filter-search')?.value?.trim() || null
    };

    try {
        const res = await fetch('api/transactions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(params)
        });
        const json = await res.json();

        const tbody = document.getElementById('transactions-body');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (json.status === 'success' && json.data && json.data.length > 0) {
            json.data.forEach(item => {
                const tr = document.createElement('tr');
                const amountClass = item.type === 'income' ? 'positive' : 'negative';
                const sign = item.type === 'income' ? '+' : '-';
                const categoryName = item.category_name || 'Без категории';

                tr.innerHTML = `
                    <td>${formatDate(item.date)}</td>
                    <td>${escapeHtml(categoryName)}</td>
                    <td>${escapeHtml(item.description || '-')}</td>
                    <td class="${amountClass}">${sign}${formatMoney(item.amount)}</td>
                    <td>
                        <button class="btn btn-small btn-secondary" onclick="editTransaction(${item.id})" title="Редактировать">✏️</button>
                        <button class="btn btn-small btn-danger" onclick="openTransDeleteModal(${item.id})" title="Удалить">🗑️</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Нет операций</td></tr>';
        }
    } catch (err) {
        console.error('Error loading transactions:', err);
        const tbody = document.getElementById('transactions-body');
        if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Ошибка загрузки</td></tr>';
    }
};

window.loadStats = async function() {
    try {
        const res = await fetch('api/transactions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_stats' })
        });
        const json = await res.json();
        if (json.status === 'success') {
            const balanceEl = document.getElementById('balance');
            const incomeEl = document.getElementById('income-total');
            const expenseEl = document.getElementById('expense-total');

            if (balanceEl) balanceEl.textContent = formatMoney(json.data.balance);
            if (incomeEl) incomeEl.textContent = formatMoney(json.data.total_income);
            if (expenseEl) expenseEl.textContent = formatMoney(json.data.total_expense);
        }
    } catch (err) {
        console.error('Load stats error:', err);
    }
};

window.loadChartData = async function() {
    const ctx = document.getElementById('expenseChart');
    const noDataEl = document.getElementById('no-chart-data');

    if (!ctx) return;

    if (expenseChart) {
        expenseChart.destroy();
        expenseChart = null;
    }

    try {
        const res = await fetch('api/transactions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_expense_by_category' })
        });
        const json = await res.json();

        if (json.status === 'success' && json.data && json.data.length > 0) {
            if (noDataEl) noDataEl.style.display = 'none';
            ctx.style.display = 'block';

            const labels = json.data.map(item => item.category_name || 'Без категории');
            const values = json.data.map(item => parseFloat(item.total_amount));

            // ИСПРАВЛЕНО: добавлено data: перед объектом
            expenseChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 15, font: { size: 11 } }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return formatMoney(context.parsed);
                                }
                            }
                        }
                    }
                }
            });
        } else {
            if (noDataEl) noDataEl.style.display = 'block';
            ctx.style.display = 'none';
        }
    } catch (err) {
        console.error('Chart error:', err);
    }
};

async function executeDelete(id) {
    try {
        const res = await fetch('api/transactions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: id })
        });
        const json = await res.json();
        if (json.status === 'success') {
            loadStats();
            loadTransactions();
            loadChartData();
        }
    } catch (err) {
        console.error('Delete error:', err);
    }
}

window.editTransaction = async function(id) {
    try {
        const res = await fetch('api/transactions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_one', id })
        });
        const json = await res.json();
        if (json.status === 'success') {
            const t = json.data;
            document.getElementById('trans-id').value = t.id;
            document.getElementById('trans-type').value = t.type;
            populateModalCategories();
            setTimeout(() => {
                document.getElementById('category-select').value = t.category_id || '';
            }, 50);
            document.getElementById('amount').value = t.amount;
            document.getElementById('description').value = t.description || '';
            document.getElementById('date').value = t.date;
            document.getElementById('modal-title').textContent = '✏️ Редактирование';
            document.getElementById('transaction-modal').showModal();
        }
    } catch (err) {
        console.error('Edit error:', err);
    }
};

async function loadCategoriesCache() {
    try {
        const res = await fetch('api/transactions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_categories' })
        });
        const json = await res.json();
        if (json.status === 'success') {
            allCategories = json.data;
            populateFilterCategories();
        }
    } catch (err) {
        console.error('Load categories error:', err);
    }
}

function populateFilterCategories() {
    const filterCategory = document.getElementById('filter-category');
    if (!filterCategory) return;

    filterCategory.innerHTML = '<option value="">Все</option>';
    allCategories.forEach(cat => {
        const opt = document.createElement('option');
        opt.value = cat.id;
        opt.textContent = cat.name;
        filterCategory.appendChild(opt);
    });
}

function populateModalCategories() {
    const typeSelect = document.getElementById('trans-type');
    const categorySelect = document.getElementById('category-select');
    if (!typeSelect || !categorySelect) return;

    const type = typeSelect.value;
    categorySelect.innerHTML = '<option value="">Без категории</option>';
    const filtered = allCategories.filter(c => c.type === type);
    filtered.forEach(cat => {
        const opt = document.createElement('option');
        opt.value = cat.id;
        opt.textContent = cat.name;
        categorySelect.appendChild(opt);
    });
}

window.formatMoney = function(num) {
    return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB' }).format(num || 0);
};

window.formatDate = function(dateString) {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('ru-RU', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

window.escapeHtml = function(text) {
    if (!text) return text;
    return text.replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[m]));
};

// === ИНИЦИАЛИЗАЦИЯ ===
document.addEventListener('DOMContentLoaded', function() {
    const applyBtn = document.getElementById('apply-filters');
    if (applyBtn) applyBtn.addEventListener('click', loadTransactions);

    const resetBtn = document.getElementById('reset-filters');
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            document.getElementById('filter-date-from').value = '';
            document.getElementById('filter-date-to').value = '';
            document.getElementById('filter-type').value = '';
            document.getElementById('filter-category').value = '';
            document.getElementById('filter-search').value = '';
            loadTransactions();
        });
    }

    const modal = document.getElementById('transaction-modal');
    const addBtn = document.getElementById('add-transaction');
    const form = document.getElementById('transaction-form');
    const typeSelect = document.getElementById('trans-type');

    if (addBtn && modal) {
        addBtn.addEventListener('click', () => {
            modal.showModal();
            document.getElementById('trans-id').value = '';
            document.getElementById('modal-title').textContent = '➕ Новая операция';
            form.reset();
            document.getElementById('date').valueAsDate = new Date();
            populateModalCategories();
        });
    }

    if (typeSelect) typeSelect.addEventListener('change', populateModalCategories);

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            data.action = data.id ? 'update' : 'add';
            if (!data.category_id) delete data.category_id;

            try {
                const res = await fetch('api/transactions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const json = await res.json();
                if (json.status === 'success') {
                    modal.close();
                    form.reset();
                    document.getElementById('trans-id').value = '';
                    document.getElementById('modal-title').textContent = '➕ Новая операция';
                    if (form.querySelector('input[name="date"]')) {
                        form.querySelector('input[name="date"]').valueAsDate = new Date();
                    }
                    loadStats();
                    loadTransactions();
                    loadChartData();
                } else {
                    alert('Ошибка: ' + json.message);
                }
            } catch (err) {
                console.error('Form submit error:', err);
                alert('Ошибка соединения');
            }
        });
    }

    loadCategoriesCache();
    loadStats();
    loadTransactions();
    loadChartData();
});