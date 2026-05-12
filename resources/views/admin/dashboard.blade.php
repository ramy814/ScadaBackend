<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم المدير</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
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
        .nav-links a.active { background: var(--brand); color: #fff; box-shadow: 0 4px 6px rgba(59, 130, 246, 0.2); }
        .btn-logout { 
            border: none; 
            border-radius: var(--radius-md); 
            padding: 10px 20px; 
            background: var(--danger-bg); 
            color: var(--danger); 
            font-family: 'Tajawal', sans-serif;
            font-weight: 700;
            font-size: 14px; 
            cursor: pointer; 
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-logout:hover { background: var(--danger); color: #fff; transform: translateY(-1px); }

        /* Main Content */
        .container { max-width: 1400px; margin: 0 auto; padding: 32px 24px; }
        
        .alert-success {
            background: var(--ok-bg);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: var(--ok);
            border-radius: var(--radius-md);
            padding: 16px 20px;
            margin-bottom: 24px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--shadow-sm);
        }

        /* Toolbar / Filters */
        .toolbar { 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            margin-bottom: 24px; 
            flex-wrap: wrap; 
            gap: 16px;
        }
        .tabs-group {
            display: inline-flex;
            background: var(--surface);
            padding: 6px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--stroke);
        }
        .tabs-group a {
            text-decoration: none;
            color: var(--text-secondary);
            padding: 8px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            transition: var(--transition);
        }
        .tabs-group a:hover { color: var(--text-primary); }
        .tabs-group a.active { background: var(--brand-light); color: var(--brand); }
        
        .filters-group {
            display: inline-flex;
            gap: 8px;
        }
        .filters-group a {
            text-decoration: none;
            background: var(--surface);
            border: 1px solid var(--stroke);
            color: var(--text-secondary);
            padding: 8px 16px;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }
        .filters-group a:hover { border-color: var(--brand); color: var(--brand); }
        .filters-group a.active { background: var(--text-primary); border-color: var(--text-primary); color: #fff; }

        /* Stats Grid */
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 24px; 
            margin-bottom: 32px; 
        }
        .stat-group {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(255, 255, 255, 0.8);
            position: relative;
            overflow: hidden;
        }
        .stat-group::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 4px;
        }
        .stat-group.primary::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
        .stat-group.success::before { background: linear-gradient(90deg, #10b981, #34d399); }
        .stat-group.warning::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        
        .group-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .group-title svg { width: 20px; height: 20px; }
        .primary .group-title svg { color: var(--brand); }
        .success .group-title svg { color: var(--ok); }
        .warning .group-title svg { color: var(--warn); }
        
        .stat-items { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .stat-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .stat-item .label { font-size: 12px; color: var(--text-secondary); font-weight: 600; }
        .stat-item .value { font-size: 24px; font-weight: 800; color: var(--text-primary); }

        /* Table Card */
        .table-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.8);
            overflow: hidden;
        }
        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--stroke);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.5);
        }
        .table-title { font-size: 18px; font-weight: 800; color: var(--text-primary); }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 1000px; }
        th { 
            background: #f8fafc; 
            padding: 14px 16px; 
            text-align: right; 
            font-size: 13px; 
            font-weight: 700; 
            color: var(--text-secondary);
            border-bottom: 1px solid var(--stroke);
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
            padding: 4px 10px; 
            border-radius: 999px; 
            font-size: 12px; 
            font-weight: 700; 
        }
        .badge.ok { background: var(--ok-bg); color: var(--ok); }
        .badge.warn { background: var(--warn-bg); color: var(--warn); }
        .badge.danger { background: var(--danger-bg); color: var(--danger); }
        .badge.neutral { background: #f1f5f9; color: var(--text-secondary); }

        /* Action Buttons */
        .actions-cell {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            color: #fff;
            text-decoration: none;
        }
        .btn-icon svg { width: 16px; height: 16px; }
        .btn-icon.view { background: var(--brand); }
        .btn-icon.view:hover { background: var(--brand-hover); transform: translateY(-2px); box-shadow: 0 4px 6px rgba(59, 130, 246, 0.2); }
        .btn-icon.approve { background: var(--ok); }
        .btn-icon.approve:hover { filter: brightness(0.9); transform: translateY(-2px); box-shadow: 0 4px 6px rgba(16, 185, 129, 0.2); }
        .btn-icon.reject { background: var(--danger); }
        .btn-icon.reject:hover { filter: brightness(0.9); transform: translateY(-2px); box-shadow: 0 4px 6px rgba(239, 68, 68, 0.2); }
        .btn-icon.clarify { background: var(--warn); }
        .btn-icon.clarify:hover { filter: brightness(0.9); transform: translateY(-2px); box-shadow: 0 4px 6px rgba(245, 158, 11, 0.2); }
        
        .action-form { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
        .note-input {
            border: 1px solid var(--stroke);
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 12px;
            font-family: 'Tajawal', sans-serif;
            width: 140px;
            transition: var(--transition);
            background: #f8fafc;
        }
        .note-input:focus {
            outline: none;
            border-color: var(--brand);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .footer-note {
            margin-top: 24px;
            text-align: center;
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 500;
        }

        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
            .top-navbar { flex-direction: column; align-items: flex-start; gap: 16px; }
        }
    </style>
</head>
<body>
    <div class="top-navbar">
        <div class="nav-brand">
            <div class="title">لوحة تحكم المدير</div>
            <div class="sub">متابعة المركبات والسائقين وحالة مزامنة الرحلات</div>
            <div class="nav-links">
                <a class="active" href="{{ route('admin.dashboard') }}">الداشبورد</a>
                <a href="{{ route('admin.locations') }}">المواقع</a>
                <a href="{{ route('admin.mission-types') }}">أنواع المهام</a>
                <a href="{{ route('admin.reports') }}">التقارير</a>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.logout') }}" style="margin:0;">
            @csrf
            <button class="btn-logout" type="submit">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                تسجيل خروج
            </button>
        </form>
    </div>

    <div class="container">
        @if (session('ok'))
            <div class="alert-success">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                {{ session('ok') }}
            </div>
        @endif

        <div class="toolbar">
            <div class="tabs-group">
                <a class="{{ $period === 'daily' ? 'active' : '' }}" href="{{ route('admin.dashboard', ['period' => 'daily', 'response_status' => $responseStatus]) }}">يومي</a>
                <a class="{{ $period === 'weekly' ? 'active' : '' }}" href="{{ route('admin.dashboard', ['period' => 'weekly', 'response_status' => $responseStatus]) }}">أسبوعي</a>
                <a class="{{ $period === 'monthly' ? 'active' : '' }}" href="{{ route('admin.dashboard', ['period' => 'monthly', 'response_status' => $responseStatus]) }}">شهري</a>
            </div>
            <div class="filters-group">
                <a class="{{ $responseStatus === 'all' ? 'active' : '' }}" href="{{ route('admin.dashboard', ['period' => $period, 'response_status' => 'all']) }}">كل الحالات</a>
                <a class="{{ $responseStatus === 'pending' ? 'active' : '' }}" href="{{ route('admin.dashboard', ['period' => $period, 'response_status' => 'pending']) }}">بانتظار الرد</a>
                <a class="{{ $responseStatus === 'responded' ? 'active' : '' }}" href="{{ route('admin.dashboard', ['period' => $period, 'response_status' => 'responded']) }}">تم الرد</a>
            </div>
        </div>

        <div class="stats-grid">
            <!-- Group 1: General Performance -->
            <div class="stat-group primary">
                <div class="group-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    ملخص الأداء
                </div>
                <div class="stat-items">
                    <div class="stat-item">
                        <div class="label">رحلات اليوم</div>
                        <div class="value">{{ $todayTrips }}</div>
                    </div>
                    <div class="stat-item">
                        <div class="label">المركبات الفعالة</div>
                        <div class="value">{{ $activeVehicles }}</div>
                    </div>
                    <div class="stat-item">
                        <div class="label">السائقون الفعالون</div>
                        <div class="value">{{ $activeDrivers }}</div>
                    </div>
                </div>
            </div>

            <!-- Group 2: Approvals -->
            <div class="stat-group success">
                <div class="group-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    حالات الاعتماد
                </div>
                <div class="stat-items">
                    <div class="stat-item">
                        <div class="label">المؤكدة</div>
                        <div class="value">{{ $confirmedTrips }}</div>
                    </div>
                    <div class="stat-item">
                        <div class="label">المعتمدة إداريًا</div>
                        <div class="value">{{ $approvedTrips }}</div>
                    </div>
                    <div class="stat-item">
                        <div class="label">المرفوضة</div>
                        <div class="value">{{ $rejectedTrips }}</div>
                    </div>
                </div>
            </div>

            <!-- Group 3: Clarifications -->
            <div class="stat-group warning">
                <div class="group-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    حالات التوضيح
                </div>
                <div class="stat-items">
                    <div class="stat-item">
                        <div class="label">إجمالي التوضيحات</div>
                        <div class="value">{{ $clarificationTrips }}</div>
                    </div>
                    <div class="stat-item">
                        <div class="label">بانتظار رد</div>
                        <div class="value">{{ $clarificationPendingResponse }}</div>
                    </div>
                    <div class="stat-item">
                        <div class="label">تم الرد عليها</div>
                        <div class="value">{{ $clarificationResponded }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-header">
                <div class="table-title">آخر الرحلات المرفوعة</div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>المركبة / السائق</th>
                            <th>المسار (انطلاق ← وجهة)</th>
                            <th>النقاط</th>
                            <th>الحالة الإدارية</th>
                            <th>رد السائق</th>
                            <th>الوقت</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentTrips as $trip)
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-primary);">{{ $trip->vehicle->plate_number ?? '-' }}</div>
                                    <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">{{ $trip->driver->name ?? '-' }}</div>
                                </td>
                                <td>
                                    <div style="font-weight: 600;">{{ $trip->origin_text }}</div>
                                    <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">إلى: {{ $trip->destination_text }}</div>
                                </td>
                                <td>
                                    <span class="badge neutral" title="النقاط">{{ $trip->points_received }} / {{ $trip->points_expected }}</span>
                                </td>
                                <td>
                                    @if ($trip->status === 'confirmed')
                                        <span class="badge ok">مؤكدة</span>
                                    @elseif ($trip->status === 'uploaded')
                                        <span class="badge warn">مرفوعة</span>
                                    @else
                                        <span class="badge danger">{{ $trip->status }}</span>
                                    @endif
                                    
                                    <span style="margin: 0 4px; color: var(--stroke);">|</span>

                                    @if ($trip->review_status === 'approved')
                                        <span class="badge ok">مُعتمدة</span>
                                    @elseif ($trip->review_status === 'rejected')
                                        <span class="badge danger">مرفوضة</span>
                                    @elseif ($trip->review_status === 'needs_clarification')
                                        <span class="badge warn">توضيح</span>
                                    @else
                                        <span class="badge neutral">قيد المراجعة</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($trip->clarification_response)
                                        <div style="max-width:200px; white-space:normal; line-height:1.4; font-size: 13px;">
                                            {{ $trip->clarification_response }}
                                        </div>
                                        <div style="color:var(--text-secondary); font-size:11px; margin-top:4px;">
                                            {{ optional($trip->clarification_responded_at)->format('Y-m-d H:i') }}
                                        </div>
                                    @elseif ($trip->review_status === 'needs_clarification')
                                        <span class="badge warn">بانتظار الرد</span>
                                    @else
                                        <span style="color:var(--stroke);">-</span>
                                    @endif
                                </td>
                                <td style="color: var(--text-secondary); font-size: 13px;">
                                    {{ optional($trip->started_at)->format('Y-m-d H:i') }}
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 8px;">
                                        <div class="actions-cell">
                                            <a href="{{ route('admin.trips.show', ['trip' => $trip->id]) }}" class="btn-icon view" title="التفاصيل">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                            </a>
                                            <form method="POST" action="{{ route('admin.trips.approve', ['trip' => $trip->id, 'period' => $period]) }}" style="margin:0;">
                                                @csrf
                                                <input type="hidden" name="note" value="{{ $trip->review_note }}">
                                                <button type="submit" class="btn-icon approve" title="اعتماد">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.trips.reject', ['trip' => $trip->id, 'period' => $period]) }}" style="margin:0;">
                                                @csrf
                                                <input type="hidden" name="note" value="{{ $trip->review_note }}">
                                                <button type="submit" class="btn-icon reject" title="رفض">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                                </button>
                                            </form>
                                        </div>
                                        <form method="POST" action="{{ route('admin.trips.clarify', ['trip' => $trip->id, 'period' => $period]) }}" class="action-form">
                                            @csrf
                                            <input class="note-input" name="note" placeholder="ملاحظة للتوضيح..." value="{{ $trip->review_note }}">
                                            <button type="submit" class="btn-icon clarify" title="طلب توضيح">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 48px; color: var(--text-secondary);">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 16px; opacity: 0.5;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                    <div>لا توجد رحلات ضمن الفترة المحددة.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="footer-note">
            آخر تحديث للبيانات: <span dir="ltr">{{ now()->format('Y-m-d H:i:s') }}</span>
        </div>
    </div>
</body>
</html>
