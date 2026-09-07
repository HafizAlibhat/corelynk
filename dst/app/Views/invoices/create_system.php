<!DOCTYPE html>
<html>
<head>
    <title>Create System Invoice - CoreLynk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Create System Invoice</h2>
        <form method="post" action="<?= site_url('customer-invoices/create-system') ?>">
            <div class="mb-3">
                <label>Customer</label>
                <select name="customer_id" class="form-control" required>
                    <!-- Populate from customers table -->
                </select>
            </div>
            <div class="mb-3">
                <label>Payment Terms</label>
                <select name="payment_term_id" class="form-control">
                    <!-- Populate from payment_terms table -->
                </select>
            </div>
            
            <h4>Invoice Items</h4>
            <div id="items-container">
                <div class="item-row row mb-2">
                    <div class="col-md-4">
                        <input type="text" name="items[0][description]" class="form-control" placeholder="Description" required>
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="items[0][quantity]" class="form-control" placeholder="Qty" step="0.01" required>
                    </div>
                    <div class="col-md-3">
                        <input type="number" name="items[0][unit_price]" class="form-control" placeholder="Unit Price" step="0.01" required>
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control" placeholder="Total" readonly>
                    </div>
                </div>
            </div>
            
            <button type="button" class="btn btn-secondary mb-3" onclick="addItem()">Add Item</button>
            
            <button type="submit" class="btn btn-primary">Create Invoice</button>
        </form>
    </div>
    
    <script>
        let itemCount = 1;
        function addItem() {
            const container = document.getElementById('items-container');
            const newRow = document.createElement('div');
            newRow.className = 'item-row row mb-2';
            newRow.innerHTML = `
                <div class="col-md-4">
                    <input type="text" name="items[${itemCount}][description]" class="form-control" placeholder="Description" required>
                </div>
                <div class="col-md-2">
                    <input type="number" name="items[${itemCount}][quantity]" class="form-control" placeholder="Qty" step="0.01" required>
                </div>
                <div class="col-md-3">
                    <input type="number" name="items[${itemCount}][unit_price]" class="form-control" placeholder="Unit Price" step="0.01" required>
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" placeholder="Total" readonly>
                </div>
            `;
            container.appendChild(newRow);
            itemCount++;
        }
    </script>
</body>
</html>
