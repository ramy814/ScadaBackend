<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل الرحلة #{{ $trip->id }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""
    />
    <style>
        :root {
            --bg-color: #f0f4f8;
            --surface: #ffffff;
            --stroke: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --brand: #3b82f6;
            --brand-light: #eff6ff;
            --brand-hover: #2563eb;
            --ok: #10b981;
            --ok-bg: #d1fae5;
            --warn: #f59e0b;
            --warn-bg: #fef3c7;
            --danger: #ef4444;
            --danger-bg: #fee2e2;
            --radius-lg: 16px;
            --radius-md: 12px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.05), 0 4px 6px -2px rgba(0,0,0,0.025);
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * { box-sizing: border-box; }
        
        body { 
            font-family: 'Tajawal', sans-serif; 
            background-color: var(--bg-color); 
            background-image: radial-gradient(circle at top right, #e0eaff 0%, #f0f4f8 20%);
            margin: 0; 
            color: var(--text-primary); 
            min-height: 100vh;
        }

        /* Navbar */
        .top-navbar { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            padding: 16px 32px; 
            background: rgba(255, 255, 255, 0.85); 
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.6); 
            position: sticky; 
            top: 0; 
            z-index: 50; 
            box-shadow: var(--shadow-sm);
        }
        .nav-brand .title { font-size: 22px; font-weight: 800; color: var(--text-primary); letter-spacing: -0.5px; }
        .nav-brand .sub { font-size: 13px; color: var(--text-secondary); margin-top: 4px; font-weight: 500; }
        .nav-links { display: flex; gap: 8px; margin-top: 12px; }
        .nav-links a { 
            text-decoration: none; 
            padding: 8px 16px; 
            border-radius: var(--radius-md); 
            font-size: 14px; 
            font-weight: 600;
            color: var(--text-secondary); 
            transition: var(--transition);
        }
        .nav-links a:hover { background: var(--brand-light); color: var(--brand); }
        .btn-back { 
            border: none; 
            border-radius: var(--radius-md); 
            padding: 10px 20px; 
            background: var(--brand); 
            color: #fff; 
            text-decoration: none;
            font-weight: 700;
            font-size: 14px; 
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-back:hover { background: var(--brand-hover); transform: translateY(-1px); box-shadow: 0 4px 6px rgba(59, 130, 246, 0.2); }

        /* Main Content */
        .container { max-width: 1400px; margin: 0 auto; padding: 32px 24px; display: grid; gap: 24px; }
        
        /* Stats Grid */
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(5, 1fr); 
            gap: 16px; 
        }
        .stat-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(255, 255, 255, 0.8);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .stat-card .label { font-size: 13px; color: var(--text-secondary); font-weight: 600; }
        .stat-card .value { font-size: 26px; font-weight: 800; color: var(--text-primary); }

        /* Two Column Grid */
        .two-cols { display: grid; grid-template-columns: 1.3fr 1fr; gap: 24px; }
        
        /* Panel Styles */
        .panel {
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.8);
            overflow: hidden;
        }
        .panel-header {
            padding: 16px 24px;
            border-bottom: 1px solid var(--stroke);
            background: rgba(255, 255, 255, 0.5);
            font-size: 16px; 
            font-weight: 800; 
            color: var(--text-primary);
        }
        .panel-body { padding: 24px; }
        
        /* Key-Value Pairs */
        .kv-group { display: flex; flex-direction: column; gap: 16px; }
        .kv-item { display: grid; grid-template-columns: 140px 1fr; gap: 12px; align-items: center; }
        .kv-label { color: var(--text-secondary); font-size: 14px; font-weight: 600; }
        .kv-val { font-size: 14px; font-weight: 700; color: var(--text-primary); }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; }

        /* Map */
        #routeMap { height: 400px; width: 100%; z-index: 10;}

        /* Tables */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        th { 
            background: #f8fafc; 
            padding: 14px 16px; 
            text-align: right; 
            font-size: 13px; 
            font-weight: 700; 
            color: var(--text-secondary);
            border-bottom: 1px solid var(--stroke);
            border-top: 1px solid var(--stroke);
        }
        td { 
            padding: 16px; 
            border-bottom: 1px solid var(--stroke); 
            font-size: 14px; 
            vertical-align: middle;
            color: var(--text-primary);
            font-weight: 500;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(248, 250, 252, 0.5); }
        
        /* Badges */
        .badge { 
            display: inline-flex; 
            align-items: center;
            justify-content: center;
            padding: 6px 12px; 
            border-radius: 999px; 
            font-size: 12px; 
            font-weight: 800; 
        }
        .badge.ok { background: var(--ok-bg); color: var(--ok); }
        .badge.warn { background: var(--warn-bg); color: var(--warn); }
        .badge.danger { background: var(--danger-bg); color: var(--danger); }
        .badge.neutral { background: #f1f5f9; color: var(--text-secondary); }

        /* Alert boxes for notes */
        .alert-box {
            background: #f8fafc;
            border: 1px solid var(--stroke);
            border-radius: var(--radius-md);
            padding: 12px 16px;
            margin-top: 6px;
            font-size: 13px;
            line-height: 1.5;
            color: var(--text-primary);
        }

        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .two-cols { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .top-navbar { flex-direction: column; align-items: flex-start; gap: 16px; }
        }
    </style>
</head>
<body>
    <div class="top-navbar">
        <div class="nav-brand">
            <div class="title">تفاصيل الرحلة #{{ $trip->id }}</div>
            <div class="sub">تتبع مسار الرحلة، مراجعة التوقفات، والتحقق من النقاط المستلمة.</div>
            <div class="nav-links">
                <a href="{{ route('admin.dashboard') }}">الداشبورد</a>
                <a href="{{ route('admin.locations') }}">المواقع</a>
                <a href="{{ route('admin.mission-types') }}">أنواع المهام</a>
                <a href="{{ route('admin.reports') }}">التقارير</a>
            </div>
        </div>
        <a class="btn-back" href="{{ route('admin.dashboard') }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            العودة للداشبورد
        </a>
    </div>

    <div class="container">
        <!-- Stats Highlights -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">النقاط المستلمة</div>
                <div class="value"
                    @if($trip->points_expected > 0)
                        style="color: {{ round(($trip->points_received / $trip->points_expected) * 100) >= 80 ? 'var(--ok)' : (round(($trip->points_received / $trip->points_expected) * 100) >= 50 ? 'var(--warn)' : 'var(--danger)') }}"
                    @endif>
                    {{ $trip->points_received }} / {{ $trip->points_expected }}
                </div>
            </div>
            <div class="stat-card">
                <div class="label">المسافة التقريبية</div>
                <div class="value">{{ number_format($distanceKm, 2) }} <span style="font-size:14px;color:var(--text-secondary)">كم</span></div>
            </div>
            <div class="stat-card">
                <div class="label">مدة الرحلة</div>
                <div class="value">{{ $tripDurationMinutes ?? '-' }} <span style="font-size:14px;color:var(--text-secondary)">دقيقة</span></div>
            </div>
            <div class="stat-card">
                <div class="label">وقت الانتظار</div>
                @php
                    $totalWait = max((int)($stopsMinutes ?? 0), (int)($trip->wait_minutes ?? 0));
                @endphp
                <div class="value" @if($totalWait > 0) style="color: var(--warn)" @endif>
                    @if($totalWait > 0)
                        {{ $totalWait }}
                        <span style="font-size:14px;color:var(--warn)">دقيقة</span>
                    @else
                        <span style="font-size:18px; color: var(--text-secondary); font-weight:600">لا يوجد</span>
                    @endif
                </div>
            </div>
            <div class="stat-card">
                <div class="label">الوقود الافتراضي</div>
                <div class="value">{{ $trip->virtual_fuel_liters ?? '-' }} <span style="font-size:14px;color:var(--text-secondary)">لتر</span></div>
            </div>
        </div>

        <!-- Info Panels -->
        <div class="two-cols">
            <div class="panel">
                <div class="panel-header">معلومات المهمة</div>
                <div class="panel-body">
                    <div class="kv-group">
                        <div class="kv-item"><div class="kv-label">مسار الرحلة</div><div class="kv-val">{{ $trip->origin_text }} <span style="color:var(--text-secondary); margin:0 4px;">←</span> {{ $trip->destination_text }}</div></div>
                        <div class="kv-item"><div class="kv-label">نوع المهمة</div><div class="kv-val">{{ $trip->mission_type ?? '-' }}</div></div>
                        <div class="kv-item"><div class="kv-label">بداية الرحلة</div><div class="kv-val mono">{{ optional($trip->started_at)->format('Y-m-d H:i:s') }}</div></div>
                        <div class="kv-item"><div class="kv-label">نهاية الرحلة</div><div class="kv-val mono">{{ optional($trip->ended_at)->format('Y-m-d H:i:s') ?? '-' }}</div></div>
                        <div class="kv-item">
                            <div class="kv-label">حالة الرفع</div>
                            <div class="kv-val">
                                <span class="badge {{ $trip->status === 'confirmed' ? 'ok' : 'neutral' }}">{{ $trip->status }}</span>
                            </div>
                        </div>
                        <div class="kv-item">
                            <div class="kv-label">مراجعة الإدارة</div>
                            <div class="kv-val">
                                @if ($trip->review_status === 'approved')
                                    <span class="badge ok">مُعتمدة</span>
                                @elseif ($trip->review_status === 'rejected')
                                    <span class="badge danger">مرفوضة</span>
                                @elseif ($trip->review_status === 'needs_clarification')
                                    <span class="badge warn">تحتاج توضيح</span>
                                @else
                                    <span class="badge neutral">قيد المراجعة</span>
                                @endif
                            </div>
                        </div>

                        @if ($trip->notes)
                        <div class="kv-item" style="align-items: flex-start;">
                            <div class="kv-label" style="margin-top: 10px;">ملاحظات السائق</div>
                            <div class="alert-box" style="border-right: 4px solid var(--text-secondary);">{{ $trip->notes }}</div>
                        </div>
                        @endif

                        @if ($trip->review_note)
                        <div class="kv-item" style="align-items: flex-start;">
                            <div class="kv-label" style="margin-top: 10px;">ملاحظة الإدارة</div>
                            <div class="alert-box" style="border-right: 4px solid var(--brand);">{{ $trip->review_note }}</div>
                        </div>
                        @endif

                        @if ($trip->clarification_response)
                        <div class="kv-item" style="align-items: flex-start;">
                            <div class="kv-label" style="margin-top: 10px;">رد السائق على التوضيح</div>
                            <div class="alert-box" style="border-right: 4px solid var(--warn);">{{ $trip->clarification_response }}
                                @if($trip->clarification_responded_at)
                                    <div style="font-size:11px; color:var(--text-secondary); margin-top:6px;">{{ optional($trip->clarification_responded_at)->format('Y-m-d H:i') }}</div>
                                @endif
                            </div>
                        </div>
                        @endif

                        <div class="kv-item"><div class="kv-label">وقود افتراضي</div><div class="kv-val">{{ $trip->virtual_fuel_liters ?? '-' }} <span style="font-size:12px;color:var(--text-secondary)">لتر</span></div></div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">بيانات السائق والمركبة</div>
                <div class="panel-body">
                    <div class="kv-group">
                        <div class="kv-item"><div class="kv-label">السائق</div><div class="kv-val">{{ $trip->driver_name ?? '-' }}</div></div>
                        <div class="kv-item"><div class="kv-label">رقم الجوال</div><div class="kv-val mono" style="direction: ltr; text-align: right;">{{ $trip->driver_phone ?? '-' }}</div></div>
                        <div class="kv-item"><div class="kv-label">رقم الموظف</div><div class="kv-val mono">{{ $trip->driver_employee_number ?? '-' }}</div></div>
                        <div style="height: 1px; background: var(--stroke); margin: 8px 0;"></div>
                        <div class="kv-item"><div class="kv-label">المركبة</div><div class="kv-val">{{ $trip->vehicle_name ?? '-' }}</div></div>
                        <div class="kv-item"><div class="kv-label">رقم اللوحة</div><div class="kv-val"><span class="badge neutral mono">{{ $trip->vehicle_plate ?? '-' }}</span></div></div>
                        <div class="kv-item"><div class="kv-label">نوع المركبة</div><div class="kv-val">{{ $trip->vehicle_type ?? '-' }}</div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Map -->
        <div class="panel">
            <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div>مسار الرحلة الفعلي (GPS)</div>
                <span class="badge neutral">{{ $points->count() }} نقطة مسجلة</span>
            </div>
            <div id="routeMap"></div>
        </div>

        <!-- Stops Table -->
        <div class="panel">
            <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div>التوقفات المسجلة</div>
                <span class="badge {{ $trip->stops->count() > 0 ? 'warn' : 'neutral' }}">{{ $trip->stops->count() }} توقف</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>وقت بدء التوقف</th>
                            <th>وقت استئناف الحركة</th>
                            <th>مدة التوقف</th>
                            <th>سبب التوقف</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($trip->stops as $i => $stop)
                            @php
                                $stopDuration = ($stop->started_at && $stop->ended_at)
                                    ? max(0, $stop->started_at->diffInMinutes($stop->ended_at))
                                    : null;
                            @endphp
                            <tr>
                                <td style="font-weight:700; color:var(--text-secondary);">{{ $i + 1 }}</td>
                                <td class="mono">{{ optional($stop->started_at)->format('Y-m-d H:i:s') }}</td>
                                <td class="mono">{{ optional($stop->ended_at)->format('Y-m-d H:i:s') ?? '-' }}</td>
                                <td>
                                    @if($stopDuration !== null)
                                        <span class="badge warn">{{ $stopDuration }} دقيقة</span>
                                    @else
                                        <span style="color:var(--text-secondary);">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($stop->reason)
                                        <span class="badge neutral">{{ $stop->reason }}</span>
                                    @else
                                        <span style="color:var(--text-secondary);">-</span>
                                    @endif
                                </td>
                                <td style="color: var(--text-secondary);">{{ $stop->notes ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 32px;">
                                    @if(($trip->wait_minutes ?? 0) > 0)
                                        <div style="color:var(--warn); font-size:14px; font-weight:600;">
                                            تم تسجيل إجمالي وقت انتظار ({{ $trip->wait_minutes }} دقيقة) دون تحديد مواقع توقف دقيقة.
                                        </div>
                                    @else
                                        <div style="color:var(--text-secondary); font-size:14px;">لم يسجل السائق أي توقف خلال هذه الرحلة.</div>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Passengers Table -->
        <div class="panel">
            <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div>الركاب المرافقون</div>
                <span class="badge {{ $trip->passengers->count() > 0 ? 'ok' : 'neutral' }}">{{ $trip->passengers->count() }} راكب</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم الراكب</th>
                            <th>الرقم الوظيفي</th>
                            <th>طريقة الإضافة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($trip->passengers as $i => $passenger)
                            <tr>
                                <td style="font-weight:700; color:var(--text-secondary);">{{ $i + 1 }}</td>
                                <td style="font-weight: 700;">{{ $passenger->name }}</td>
                                <td class="mono">{{ $passenger->employee_id ?? '-' }}</td>
                                <td>
                                    @php
                                        $sourceLabels = [
                                            'free_text'     => ['label' => 'نص حر', 'class' => 'neutral'],
                                            'employee_list' => ['label' => 'من قائمة الموظفين', 'class' => 'ok'],
                                            'manual'        => ['label' => 'يدوي', 'class' => 'warn'],
                                        ];
                                        $src = $sourceLabels[$passenger->source] ?? ['label' => $passenger->source, 'class' => 'neutral'];
                                    @endphp
                                    <span class="badge {{ $src['class'] }}">{{ $src['label'] }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 32px;">
                                    <div style="color:var(--text-secondary); font-size:14px;">لم يُسجّل السائق أي ركاب لهذه الرحلة.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tracking Points -->
        <div class="panel">
            <div class="panel-header">السجل الكامل لنقاط التتبع</div>
            <div class="table-wrap" style="max-height: 400px; overflow-y: auto;">
                <table>
                    <thead style="position: sticky; top: 0; z-index: 5;">
                        <tr>
                            <th>#</th>
                            <th>الوقت</th>
                            <th>الإحداثيات (Lat, Lng)</th>
                            <th>معدل الدقة</th>
                            <th>السرعة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($points as $point)
                            <tr>
                                <td style="font-weight: 700; color: var(--text-secondary);">{{ $point->sequence_number }}</td>
                                <td class="mono">{{ optional($point->captured_at)->format('Y-m-d H:i:s') }}</td>
                                <td>
                                    <a href="https://maps.google.com/?q={{ $point->latitude }},{{ $point->longitude }}" target="_blank" class="badge neutral mono" style="text-decoration: none; transition: var(--transition);">
                                        {{ $point->latitude }}, {{ $point->longitude }}
                                    </a>
                                </td>
                                <td>{{ $point->accuracy ?? '-' }} <span style="font-size:12px;color:var(--text-secondary)">متر</span></td>
                                <td>{{ $point->speed ?? '-' }} <span style="font-size:12px;color:var(--text-secondary)">كم/س</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 24px;">السجل فارغ.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Leaflet JS -->
    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""
    ></script>
    <script>
        (function () {
            const points = @json($mappedPoints);

            const map = L.map('routeMap');
            map.setView([31.5017, 34.4668], 12); // Default to Gaza

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap',
            }).addTo(map);

            if (!points.length) {
                return;
            }

            const latLngs = points.map((p) => [p.lat, p.lng]);
            const route = L.polyline(latLngs, {
                color: '#3b82f6',
                weight: 5,
                opacity: 0.8,
            }).addTo(map);

            const start = points[0];
            const end = points[points.length - 1];
            
            // Custom icons can be defined here, using default for now
            L.marker([start.lat, start.lng]).addTo(map).bindPopup('<b>نقطة الانطلاق</b>');
            L.marker([end.lat, end.lng]).addTo(map).bindPopup('<b>نقطة الوصول</b>');

            map.fitBounds(route.getBounds(), { padding: [32, 32] });
        })();
    </script>
</body>
</html>
