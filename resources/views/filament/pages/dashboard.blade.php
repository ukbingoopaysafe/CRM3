<x-filament-panels::page>
    <div class="cb-dashboard">
        {{-- Hero: who you are, what today looks like, and the three numbers
             that decide the day before any list is opened. --}}
        <section class="cb-hero" aria-labelledby="cb-greeting">
            <div class="cb-hero-main">
                <span class="cb-avatar" aria-hidden="true">{{ $hero['initials'] ?? '' }}</span>

                <div>
                    <span class="cb-hero-eyebrow">{{ $hero['date'] ?? '' }}</span>

                    <h1 class="cb-hero-greeting" id="cb-greeting">
                        {{ now()->hour < 12 ? 'صباح الخير' : 'مساء الخير' }}، {{ $hero['name'] ?? '' }}
                    </h1>

                    <div class="cb-hero-meta">
                        <span class="cb-hero-badge">
                            <x-filament::icon icon="heroicon-m-shield-check" class="h-3.5 w-3.5" />
                            {{ $hero['role'] ?? '' }}
                        </span>

                        @if (filled($hero['departments'] ?? null))
                            <span>{{ $hero['departments'] }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="cb-hero-stats">
                <div class="cb-hero-stat">
                    <span class="cb-hero-stat-value cb-num">{{ $hero['active'] ?? 0 }}</span>
                    <span class="cb-hero-stat-label">طلبات نشطة</span>
                </div>

                <div @class(['cb-hero-stat', 'cb-hero-stat--alert' => ($hero['overdue'] ?? 0) > 0])>
                    <span class="cb-hero-stat-value cb-num">{{ $hero['overdue'] ?? 0 }}</span>
                    <span class="cb-hero-stat-label">متابعات متأخرة</span>
                </div>

                <div class="cb-hero-stat cb-hero-stat--today">
                    <span class="cb-hero-stat-value cb-num">{{ $hero['due_today'] ?? 0 }}</span>
                    <span class="cb-hero-stat-label">متابعات اليوم</span>
                </div>
            </div>
        </section>

        @foreach ($boards as $board)
            <section class="cb-board" aria-labelledby="cb-board-{{ $board['key'] }}">
                <header class="cb-board-head">
                    <h2 class="cb-board-title" id="cb-board-{{ $board['key'] }}">{{ $board['label'] }}</h2>

                    <div class="cb-board-links">
                        <a class="cb-link" href="{{ $board['urls']['new_request'] }}">
                            <x-filament::icon icon="heroicon-m-plus" class="h-4 w-4" />
                            طلب جديد
                        </a>

                        <a class="cb-link" href="{{ $board['urls']['new_customer'] }}">
                            <x-filament::icon icon="heroicon-m-user-plus" class="h-4 w-4" />
                            عميل جديد
                        </a>

                        <a class="cb-link" href="{{ $board['urls']['requests'] }}">
                            كل الطلبات
                            <x-filament::icon icon="heroicon-m-arrow-left" class="h-4 w-4" />
                        </a>
                    </div>
                </header>

                <div class="cb-stats">
                    <div class="cb-stat cb-stat--success">
                        <div class="cb-stat-top">
                            <span class="cb-stat-icon">
                                <x-filament::icon icon="heroicon-o-clipboard-document-list" />
                            </span>

                            {{-- One text run: splitting the number into its own
                                 element lets RTL reorder the sign to "6+". --}}
                            <span class="cb-stat-chip">
                                +{{ $board['new_this_week'] }} هذا الأسبوع
                            </span>
                        </div>

                        <span class="cb-stat-value cb-num">{{ $board['metrics']['active'] }}</span>
                        <p class="cb-stat-label">طلبات نشطة</p>
                    </div>

                    <div @class(['cb-stat', 'cb-stat--danger' => $board['metrics']['overdue'] > 0])>
                        <div class="cb-stat-top">
                            <span class="cb-stat-icon">
                                <x-filament::icon icon="heroicon-o-exclamation-triangle" />
                            </span>

                            <span class="cb-stat-chip">
                                {{ $board['metrics']['overdue'] > 0 ? 'تحتاج تدخّل' : 'لا متأخرات' }}
                            </span>
                        </div>

                        <span class="cb-stat-value cb-num">{{ $board['metrics']['overdue'] }}</span>
                        <p class="cb-stat-label">متابعات متأخرة</p>
                    </div>

                    <div class="cb-stat cb-stat--warning">
                        <div class="cb-stat-top">
                            <span class="cb-stat-icon">
                                <x-filament::icon icon="heroicon-o-clock" />
                            </span>

                            <span class="cb-stat-chip">حتى نهاية اليوم</span>
                        </div>

                        <span class="cb-stat-value cb-num">{{ $board['metrics']['due_today'] }}</span>
                        <p class="cb-stat-label">متابعات اليوم</p>
                    </div>

                    <div class="cb-stat cb-stat--info">
                        <div class="cb-stat-top">
                            <span class="cb-stat-icon">
                                <x-filament::icon icon="heroicon-o-user" />
                            </span>

                            <span class="cb-stat-chip">وسيط فقط</span>
                        </div>

                        <span class="cb-stat-value cb-num">{{ $board['metrics']['without_customer'] }}</span>
                        <p class="cb-stat-label">بلا عميل معروف</p>
                    </div>
                </div>

                {{-- The counters that matter but do not deserve a card of their own. --}}
                <div class="cb-chips">
                    <div class="cb-chip-card">
                        <span class="cb-chip-card-label">طلبات متوقفة مؤقتًا</span>
                        <span class="cb-chip-card-value cb-num">{{ $board['metrics']['paused'] }}</span>
                    </div>

                    <div @class(['cb-chip-card', 'cb-chip-card--alert' => $board['metrics']['aftercare_overdue'] > 0])>
                        <span class="cb-chip-card-label">ما بعد الصفقة: متأخرة</span>
                        <span class="cb-chip-card-value cb-num">{{ $board['metrics']['aftercare_overdue'] }}</span>
                    </div>

                    <div class="cb-chip-card">
                        <span class="cb-chip-card-label">ما بعد الصفقة: اليوم</span>
                        <span class="cb-chip-card-value cb-num">{{ $board['metrics']['aftercare_due_today'] }}</span>
                    </div>
                </div>

                <div class="cb-split">
                    <div class="cb-panel">
                        <div class="cb-panel-head">
                            <h3 class="cb-panel-title">
                                متابعات متأخرة تحتاج قرارًا
                                @if ($board['metrics']['overdue'] > 0)
                                    <span class="cb-panel-count cb-num">{{ $board['metrics']['overdue'] }}</span>
                                @endif
                            </h3>

                            <a class="cb-link" href="{{ $board['urls']['requests'] }}">عرض الكل</a>
                        </div>

                        @if ($board['overdue'] === [])
                            <p class="cb-quiet">لا توجد متابعة متأخرة في {{ $board['label'] }}. كل الملفات في موعدها.</p>
                        @else
                            <ul class="cb-list">
                                @foreach ($board['overdue'] as $row)
                                    <li>
                                        <a class="cb-list-item" href="{{ $row['url'] }}">
                                            <span class="cb-list-main">
                                                <span class="cb-list-title">{{ $row['title'] }}</span>
                                                <span class="cb-list-meta">
                                                    {{ $row['stage'] }}
                                                </span>
                                            </span>

                                            <span class="cb-list-trail">
                                                <span class="cb-late">{{ $row['late_days'] }} يوم</span>
                                                <x-filament::icon icon="heroicon-m-chevron-left" class="h-4 w-4" />
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="cb-panel">
                        <div class="cb-panel-head">
                            <h3 class="cb-panel-title">أحدث الطلبات</h3>

                            <a class="cb-link" href="{{ $board['urls']['requests'] }}">عرض الكل</a>
                        </div>

                        @if ($board['latest'] === [])
                            <div class="cb-quiet">
                                <p>لم يُسجَّل أي طلب في {{ $board['label'] }} بعد.</p>
                                <a class="cb-link" href="{{ $board['urls']['new_request'] }}">ابدأ بأول طلب</a>
                            </div>
                        @else
                            <ul class="cb-list">
                                @foreach ($board['latest'] as $row)
                                    <li>
                                        <a class="cb-list-item" href="{{ $row['url'] }}">
                                            <span class="cb-list-main">
                                                <span class="cb-list-title">{{ $row['title'] }}</span>
                                                <span class="cb-list-meta">
                                                    {{ $row['stage'] }}
                                                    @if (filled($row['when']))
                                                        <span class="cb-list-dot">{{ $row['when'] }}</span>
                                                    @endif
                                                </span>
                                            </span>

                                            <span class="cb-list-trail">
                                                <x-filament::icon icon="heroicon-m-chevron-left" class="h-4 w-4" />
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
