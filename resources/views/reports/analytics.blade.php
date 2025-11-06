<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <style>
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 12px; }
        h1, h2, h3 { margin: 0 0 6px; }
        .muted { color: #666; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; background: #eef; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; }
        th { background: #f0f0f0; }
        .mt { margin-top: 12px; }
    </style>
</head>
<body>
    <h1>Valenzuela City — Survey Analytics Report</h1>
    <div class="muted">Generated: {{ $generatedAt }} @if($service) — Filter: <span class="badge">{{ $service }}</span>@endif</div>

    <h3 class="mt">Summary</h3>
    @php
        $total = $stats['total_responses'] ?? 0;
        $overall = $stats['overall_average'] ?? null;
        $pct = $overall ? round((($overall-1)/4)*100) : 0;
        $qa = $stats['question_averages'] ?? [];
        $labels = [
            'A1'=>'Staff Courtesy','A2'=>'Process Simplicity','A3'=>'Timeliness','A4'=>'Requirements Clarity',
            'B1'=>'Cleanliness','B2'=>'Waiting Comfort','C1'=>'Met Expectations','C2'=>'Overall Quality','C3'=>'Would Recommend'
        ];
        $top = null; $low = null;
        foreach($labels as $k=>$lab){ $v = $qa[$k] ?? 0; if(!$top||$v>$top['v']) $top=['k'=>$k,'label'=>$lab,'v'=>$v]; if($v && (!$low||$v<$low['v'])) $low=['k'=>$k,'label'=>$lab,'v'=>$v]; }
    @endphp
    <p>Overall satisfaction is approximately <strong>{{ $pct }}%</strong> based on <strong>{{ $total }}</strong> responses.
        Strongest area: <strong>{{ $top['label'] ?? 'N/A' }}</strong> ({{ isset($top['v']) ? number_format($top['v'],2) : '—' }}).
        Area needing attention: <strong>{{ $low['label'] ?? 'N/A' }}</strong>@if(isset($low['v'])) ({{ number_format($low['v'],2) }})@endif.
    </p>

    <h3 class="mt">Rating Breakdown</h3>
    @php $bd = $stats['rating_breakdown'] ?? []; $bdTotal = array_sum($bd); @endphp
    <table>
        <thead><tr><th style="width:30%">Label</th><th style="width:10%">Count</th><th>Graph</th></tr></thead>
        <tbody>
            @foreach($bd as $label=>$count)
                @php $pct = $bdTotal ? round(($count/$bdTotal)*100) : 0; @endphp
                <tr>
                    <td>{{ $label }}</td>
                    <td>{{ $count }}</td>
                    <td>
                        <div style="background:#f3f4f6; height:10px; border-radius:6px;">
                            <div style="background:#7885bf; width: <?php echo $pct; ?>%; height:10px; border-radius:6px;"></div>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3 class="mt">Question Averages</h3>
    @php 
        $qa = $stats['question_averages'] ?? []; 
        $labels = [
            'A1'=>'Staff Courtesy','A2'=>'Process Simplicity','A3'=>'Timeliness','A4'=>'Requirements Clarity',
            'B1'=>'Cleanliness','B2'=>'Waiting Comfort','C1'=>'Met Expectations','C2'=>'Overall Quality','C3'=>'Would Recommend'
        ];
    @endphp
    <table>
        <thead><tr><th style="width:30%">Question</th><th style="width:10%">Avg</th><th>Graph</th></tr></thead>
        <tbody>
            @foreach($labels as $k=>$lab)
                @php $avg = $qa[$k] ?? null; $pct = $avg ? round(($avg/5)*100) : 0; @endphp
                <tr>
                    <td>{{ $k }} — {{ $lab }}</td>
                    <td>{{ $avg ? number_format($avg,2) : '—' }}</td>
                    <td>
                        <div style="background:#f3f4f6; height:10px; border-radius:6px;">
                            <div style="background:#22c55e; width: <?php echo $pct; ?>%; height:10px; border-radius:6px;"></div>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3 class="mt">Sample Responses</h3>
    @php $ratingKeys=['A1','A2','A3','A4','B1','B2','C1','C2','C3']; @endphp
    <table>
        <thead>
            <tr>
                <th>ID</th><th>Service</th><th>Date</th><th>Barangay</th><th>Name</th>
                @foreach($ratingKeys as $rk)<th>{{ $rk }}</th>@endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($items as $it)
                @php $map = []; foreach($it->ratings as $r){ $map[$r->question_id]=$r->value; } @endphp
                <tr>
                    <td>{{ $it->id }}</td>
                    <td>{{ $it->service }}</td>
                    <td>{{ optional($it->created_at)->toDateTimeString() }}</td>
                    <td>{{ $it->barangay }}</td>
                    <td>{{ $it->name }}</td>
                    @foreach($ratingKeys as $rk)<td>{{ $map[$rk] ?? '' }}</td>@endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
