<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Inventory Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; color: #333; }
        .header { text-align: center; margin-bottom: 20px; padding: 15px; background: #f3f4f6; }
        .header h1 { font-size: 24px; margin-bottom: 5px; }
        .header p { color: #666; font-size: 11px; }
        .summary { display: flex; justify-content: space-around; margin: 20px 0; padding: 15px; background: #f9fafb; border-radius: 5px; }
        .summary-item { text-align: center; }
        .summary-item .value { font-size: 20px; font-weight: bold; color: #3b82f6; }
        .summary-item .label { font-size: 10px; color: #666; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #1f2937; color: white; font-weight: bold; font-size: 10px; text-transform: uppercase; }
        tr:nth-child(even) { background: #f9fafb; }
        .badge { padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-damaged { background: #fed7aa; color: #92400e; }
        .badge-lost { background: #fecaca; color: #991b1b; }
        .badge-disposed { background: #e5e7eb; color: #374151; }
        .low-stock { color: #dc2626; font-weight: bold; }
        .footer { text-align: center; margin-top: 30px; padding: 10px; font-size: 10px; color: #666; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Inventory Report</h1>
        <p>Generated on {{ $date }}</p>
    </div>

    <div class="summary">
        <div class="summary-item">
            <div class="value">{{ $summary['total_items'] }}</div>
            <div class="label">Total Items</div>
        </div>
        <div class="summary-item">
            <div class="value">ETB {{ number_format($summary['total_value'], 2) }}</div>
            <div class="label">Total Value</div>
        </div>
        <div class="summary-item">
            <div class="value">{{ $summary['active_items'] }}</div>
            <div class="label">Active Items</div>
        </div>
        <div class="summary-item">
            <div class="value">{{ $summary['low_stock'] }}</div>
            <div class="label">Low Stock Items</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Item Code</th>
                <th>Name</th>
                <th>Category</th>
                <th>Stock</th>
                <th>Location</th>
                <th>Status</th>
                <th>Purchase Price</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td>{{ $item->item_code }}</td>
                <td>{{ $item->name }}</td>
                <td>{{ $item->category }}</td>
                <td class="{{ $item->current_stock < 5 ? 'low-stock' : '' }}">
                    {{ $item->current_stock }} {{ $item->unit }}
                </td>
                <td>{{ $item->location ?? 'N/A' }}</td>
                <td>
                    <span class="badge badge-{{ strtolower($item->status) }}">
                        {{ $item->status }}
                    </span>
                </td>
                <td>ETB {{ number_format($item->purchase_price, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Finot Church Management System - Inventory Report</p>
    </div>
</body>
</html>
