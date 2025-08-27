@php($notice = session('woo_sales_notice'))
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Woo Sales</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <style>
        body { font-family: Arial, Helvetica, sans-serif; padding: 16px; }
        .filters { margin-bottom: 16px; }
        .filters label { margin-right: 8px; }
        .notice { background: #fff3cd; border: 1px solid #ffeeba; padding: 10px; margin-bottom: 10px; }
        table.dataTable tbody tr.type-order { background: #f7f7f7; font-weight: bold; }
    </style>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
</head>
<body>
    @if($notice)
        <div class="notice">{{ $notice }}</div>
    @endif

    <form id="filters" class="filters">
        <label>Date from <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
        <label>Date to <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
        <label>Order ID <input type="text" name="order_id" value="{{ $filters['order_id'] ?? '' }}" style="width: 110px;"></label>
        <label>Status(es)
            <select name="statuses[]" multiple size="4">
                @php($opts = ['pending','processing','completed','on-hold','cancelled','refunded','failed'])
                @foreach($opts as $opt)
                    <option value="{{ $opt }}" @if(in_array($opt, $filters['statuses'] ?? [])) selected @endif>{{ $opt }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit">Apply</button>
        <a id="exportBtn" href="#">Export Excel</a>
    </form>

    <table id="sales" class="display" style="width:100%">
        <thead>
        <tr>
            <th>type</th>
            <th>customer_name</th>
            <th>customer_phone</th>
            <th>order_id</th>
            <th>invoice_id</th>
            <th>customer_address</th>
            <th>customer_city</th>
            <th>customer_country</th>
            <th>order_status</th>
            <th>payment_method</th>
            <th>payment_reference</th>
            <th>wallet_amount</th>
            <th>discount</th>
            <th>coupon</th>
            <th>shipping</th>
            <th>order_total</th>
            <th>product_name</th>
            <th>sku</th>
            <th>categories</th>
            <th>weight</th>
            <th>quantity</th>
            <th>item_cost_without_vat</th>
            <th>coupon_discounted_amount</th>
            <th>total_vat_amount</th>
        </tr>
        </thead>
    </table>

    <script>
        const base = '{{ route('woo-sales.index') }}'.replace(/\/?$/, '');
        const dataUrl = base + '/data';
        const exportUrl = base + '/export';

        function qs() {
            const f = new FormData(document.getElementById('filters'));
            return new URLSearchParams(Array.from(f.entries()));
        }

        $('#filters').on('submit', function(e){
            e.preventDefault();
            table.ajax.url(dataUrl + '?' + qs().toString()).load();
            $('#exportBtn').attr('href', exportUrl + '?' + qs().toString());
        });

        $('#exportBtn').attr('href', exportUrl + '?' + qs().toString());

        const table = new $.fn.dataTable.Api($('#sales').DataTable({
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data: function (d) { return Object.fromEntries(qs()); }
            },
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excelHtml5',
                    title: 'woo-sales',
                    exportOptions: { columns: ':visible' }
                }
            ],
            columns: [
                { data: 'type' },
                { data: 'customer_name' },
                { data: 'customer_phone' },
                { data: 'order_id' },
                { data: 'invoice_id' },
                { data: 'customer_address' },
                { data: 'customer_city' },
                { data: 'customer_country' },
                { data: 'order_status' },
                { data: 'payment_method' },
                { data: 'payment_reference' },
                { data: 'wallet_amount' },
                { data: 'discount' },
                { data: 'coupon' },
                { data: 'shipping' },
                { data: 'order_total' },
                { data: 'product_name' },
                { data: 'sku' },
                { data: 'categories' },
                { data: 'weight' },
                { data: 'quantity' },
                { data: 'item_cost_without_vat' },
                { data: 'coupon_discounted_amount' },
                { data: 'total_vat_amount' },
            ],
            rowCallback: function(row, data){
                if (data.type === 'order') {
                    $(row).addClass('type-order');
                }
            }
        }));
    </script>
</body>
</html>


