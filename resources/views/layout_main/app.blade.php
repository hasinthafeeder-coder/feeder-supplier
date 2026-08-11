<!doctype html>
<html lang="zxx">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />

    <!-- Links Of CSS File -->
    <link rel="stylesheet" href="{{ asset('assets/css/sidebar-menu.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/simplebar.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/prism.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/quill.snow.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/remixicon.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/swiper-bundle.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/jsvectormap.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}" />

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('assets/images/favicon.png') }}" />

    <!-- Title -->
    <title>Fila - Bootstrap 5 Admin Dashboard Template</title>
</head>

<body class="bg-body-bg">
    <!-- Start Preloader Area -->
    <div class="preloader" id="preloader">
        <div class="preloader">
            <div class="waviy position-relative">
                <span class="d-inline-block">F</span>
                <span class="d-inline-block">I</span>
                <span class="d-inline-block">L</span>
                <span class="d-inline-block">A</span>
            </div>
        </div>
    </div>
    <!-- End Preloader Area -->

    <!-- Start Sidebar Area -->
    <div class="sidebar-area" id="sidebar-area">
        <div class="logo position-relative d-flex align-items-center justify-content-between">
            <a href="index.html" class="d-block text-decoration-none position-relative">
                <img src="{{ asset('assets/images/logo-icon.png') }}" alt="logo-icon" />
                <span class="logo-text text-secondary fw-semibold">Fila</span>
            </a>
            <button
                class="sidebar-burger-menu-close bg-transparent py-3 border-0 opacity-0 z-n1 position-absolute top-50 end-0 translate-middle-y"
                id="sidebar-burger-menu-close">
                <span class="border-1 d-block for-dark-burger"
                    style="
              border-bottom: 1px solid #475569;
              height: 1px;
              width: 25px;
              transform: rotate(45deg);
            "></span>
                <span class="border-1 d-block for-dark-burger"
                    style="
              border-bottom: 1px solid #475569;
              height: 1px;
              width: 25px;
              transform: rotate(-45deg);
            "></span>
            </button>
            <button class="sidebar-burger-menu bg-transparent p-0 border-0" id="sidebar-burger-menu">
                <span class="border-1 d-block for-dark-burger"
                    style="border-bottom: 1px solid #475569; height: 1px; width: 25px"></span>
                <span class="border-1 d-block for-dark-burger"
                    style="
              border-bottom: 1px solid #475569;
              height: 1px;
              width: 25px;
              margin: 6px 0;
            "></span>
                <span class="border-1 d-block for-dark-burger"
                    style="border-bottom: 1px solid #475569; height: 1px; width: 25px"></span>
            </button>
        </div>

        <aside id="layout-menu" class="layout-menu menu-vertical menu active" data-simplebar>
            @php
                $menu = app(\Feeder\Core\Authorization\Services\MenuService::class)->getForUser(auth()->user());
            @endphp
            <ul class="menu-inner">
                @foreach ($menu->getSections() as $section)
                    <li class="menu-title small text-uppercase">
                        <span class="menu-title-text">{{ $section->getTitle() }}</span>
                    </li>

                    @foreach ($section->getItems() as $item)
                        <li class="menu-item">
                            @if ($item->hasChildren())
                                <a href="javascript:void(0);" class="menu-link menu-toggle">
                                    @if ($item->getIcon())
                                        <span class="material-symbols-outlined menu-icon">{{ $item->getIcon() }}</span>
                                    @endif

                                    <span class="title">{{ $item->getTitle() }}</span>
                                </a>
                                <ul class="menu-sub">
                                    @foreach ($item->getChildren() as $child)
                                        <li class="menu-item">
                                            <a href="{{ $child->getRoute() ? route($child->getRoute()) : 'javascript:void(0);' }}"
                                                class="menu-link">
                                                {{ $child->getTitle() }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <a href="{{ $item->getRoute() ? route($item->getRoute()) : 'javascript:void(0);' }}"
                                    class="menu-link">
                                    @if ($item->getIcon())
                                        <span class="material-symbols-outlined menu-icon">{{ $item->getIcon() }}</span>
                                    @endif

                                    <span class="title">{{ $item->getTitle() }}</span>
                                </a>
                            @endif
                        </li>
                    @endforeach
                @endforeach

                <li class="menu-item">
                    <a href="#" class="menu-link"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <span class="material-symbols-outlined menu-icon">logout</span>
                        <span class="title">Logout</span>
                    </a>
                </li>

                <form id="logout-form" method="POST" action="{{ route('logout') }}" style="display: none;">
                    @csrf
                </form>
            </ul>
        </aside>
    </div>
    <!-- End Sidebar Area -->

    <!-- Start Main Content Area -->
    <div class="container-fluid">
        <div class="main-content d-flex flex-column">
            <!-- Start Header Area -->
            <header class="header-area bg-white mb-4 rounded-10 border border-white" id="header-area">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="left-header-content">
                            <ul
                                class="d-flex align-items-center ps-0 mb-0 list-unstyled justify-content-center justify-content-md-start">
                                <li class="d-xl-none">
                                    <button
                                        class="header-burger-menu bg-transparent p-0 border-0 position-relative top-3"
                                        id="header-burger-menu">
                                        <span class="border-1 d-block for-dark-burger"
                                            style="border-bottom: 1px solid #475569;height: 1px;width: 25px;"></span>
                                        <span class="border-1 d-block for-dark-burger"
                                            style="border-bottom: 1px solid #475569;height: 1px;width: 25px;margin: 6px 0;"></span>
                                        <span class="border-1 d-block for-dark-burger"
                                            style="border-bottom: 1px solid #475569;height: 1px;width: 25px;"></span>
                                    </button>
                                </li>
                                <li>
                                    <form class="src-form position-relative">
                                        <input type="text" class="form-control" placeholder="Search here..." />
                                        <div
                                            class="src-btn position-absolute top-50 start-0 translate-middle-y bg-transparent p-0 border-0">
                                            <span class="material-symbols-outlined">search</span>
                                        </div>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="right-header-content mt-3 mt-md-0">
                            <ul
                                class="d-flex align-items-center justify-content-center justify-content-md-end ps-0 mb-0 list-unstyled">
                                <li class="header-right-item light-dark-item">
                                    <div class="light-dark">
                                        <button class="switch-toggle dark-btn p-0 bg-transparent lh-0 border-0"
                                            id="switch-toggle">
                                            <span class="dark"><i
                                                    class="material-symbols-outlined">dark_mode</i></span>
                                            <span class="light"><i
                                                    class="material-symbols-outlined">light_mode</i></span>
                                        </button>
                                    </div>
                                </li>
                                <li class="header-right-item messages-item">
                                    <div class="dropdown notifications noti messages">
                                        <button class="btn btn-secondary border-0 p-0 position-relative"
                                            type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <span class="material-symbols-outlined">mail</span>
                                            <span class="count bg-primary">5</span>
                                        </button>
                                        <div class="dropdown-menu dropdown-lg p-0 border-0 p-0 dropdown-menu-end">
                                            <div class="d-flex justify-content-between align-items-center title">
                                                <span class="fw-medium fs-16 text-secondary">Messages
                                                    <span class="fw-normal text-body fs-16">(03)</span></span>
                                                <button
                                                    class="p-0 m-0 bg-transparent border-0 fs-15 text-primary fw-medium">
                                                    Mark all as read
                                                </button>
                                            </div>

                                            <div style="max-height: 300px" data-simplebar>
                                                <div class="notification-menu unseen">
                                                    <a href="chat.html" class="dropdown-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0">
                                                                <img src="{{ asset('assets/images/user1.jpg') }}"
                                                                    class="rounded-circle"
                                                                    style="width: 44px; height: 44px"
                                                                    alt="images" />
                                                            </div>
                                                            <div class="flex-grow-1 ms-10">
                                                                <p class="fs-16 fw-medium text-secondary mb-2">
                                                                    Jacob Liwiski
                                                                    <span class="fs-14 fw-normal text-body ms-2">35 min
                                                                        ago</span>
                                                                </p>
                                                                <span class="fs-14-5 fw-medium d-inline-block"
                                                                    style="line-height: 1.4">Hey Victor! Could you
                                                                    please...</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                                <div class="notification-menu unseen">
                                                    <a href="chat.html" class="dropdown-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0">
                                                                <img src="{{ asset('assets/images/user2.jpg') }}"
                                                                    class="rounded-circle"
                                                                    style="width: 44px; height: 44px"
                                                                    alt="images" />
                                                            </div>
                                                            <div class="flex-grow-1 ms-10">
                                                                <p class="fs-16 fw-medium text-secondary mb-2">
                                                                    Angela Carter
                                                                    <span class="fs-14 fw-normal text-body ms-2">1 day
                                                                        ago</span>
                                                                </p>
                                                                <span class="fs-14-5 fw-medium d-inline-block"
                                                                    style="line-height: 1.4">How are you Angela? Would
                                                                    you
                                                                    please...</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                                <div class="notification-menu">
                                                    <a href="chat.html" class="dropdown-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0">
                                                                <img src="{{ asset('assets/images/user3.jpg') }}"
                                                                    class="rounded-circle"
                                                                    style="width: 44px; height: 44px"
                                                                    alt="images" />
                                                            </div>
                                                            <div class="flex-grow-1 ms-10">
                                                                <p class="fs-16 fw-medium text-secondary mb-2">
                                                                    Brad Traversy
                                                                    <span class="fs-14 fw-normal text-body ms-2">2 days
                                                                        ago</span>
                                                                </p>
                                                                <span class="fs-14-5 fw-medium d-inline-block"
                                                                    style="line-height: 1.4">Hey Brad Traversy! Could
                                                                    you
                                                                    please...</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                            </div>

                                            <a href="chat.html"
                                                class="dropdown-item text-center text-primary d-block view-all fw-medium rounded-bottom-3">
                                                <span>See All Messages</span>
                                            </a>
                                        </div>
                                    </div>
                                </li>
                                <li class="header-right-item">
                                    <div class="dropdown notifications noti">
                                        <button class="btn btn-secondary border-0 p-0 position-relative"
                                            type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <span class="material-symbols-outlined">notifications</span>
                                            <span class="count">3</span>
                                        </button>
                                        <div class="dropdown-menu dropdown-lg p-0 border-0 p-0 dropdown-menu-end">
                                            <div class="d-flex justify-content-between align-items-center title">
                                                <span class="fw-medium fs-16 text-secondary">Notifications
                                                    <span class="fw-normal text-body fs-16">(03)</span></span>
                                                <button
                                                    class="p-0 m-0 bg-transparent border-0 fs-15 text-primary fw-medium">
                                                    Clear All
                                                </button>
                                            </div>

                                            <div style="max-height: 300px" data-simplebar>
                                                <div class="notification-menu unseen">
                                                    <a href="notifications.html" class="dropdown-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0">
                                                                <i
                                                                    class="material-symbols-outlined text-primary">sms</i>
                                                            </div>
                                                            <div class="flex-grow-1 ms-3">
                                                                <p class="fs-16 fw-medium text-secondary">
                                                                    You have requested to withdrawal
                                                                </p>
                                                                <span class="fs-14 fw-medium">2 hrs ago</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                                <div class="notification-menu unseen">
                                                    <a href="notifications.html" class="dropdown-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0">
                                                                <i
                                                                    class="material-symbols-outlined text-info">person</i>
                                                            </div>
                                                            <div class="flex-grow-1 ms-3">
                                                                <p class="fs-16 fw-medium text-secondary">
                                                                    A new user added in Fila
                                                                </p>
                                                                <span class="fs-14 fw-medium">3 hrs ago</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                                <div class="notification-menu">
                                                    <a href="notifications.html" class="dropdown-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0">
                                                                <i
                                                                    class="material-symbols-outlined text-success">mark_email_unread</i>
                                                            </div>
                                                            <div class="flex-grow-1 ms-3">
                                                                <p class="fs-16 fw-medium text-secondary">
                                                                    You have requested to withdrawal
                                                                </p>
                                                                <span class="fs-14 fw-medium">1 day ago</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                            </div>

                                            <a href="notifications.html"
                                                class="dropdown-item text-center text-primary d-block view-all fw-medium rounded-bottom-3">
                                                <span>See All Notifications </span>
                                            </a>
                                        </div>
                                    </div>
                                </li>
                                <li class="header-right-item">
                                    <div class="dropdown admin-profile">
                                        <div class="d-xxl-flex align-items-center bg-transparent border-0 text-start p-0 cursor dropdown-toggle"
                                            data-bs-toggle="dropdown">
                                            <div class="flex-shrink-0 position-relative">
                                                <img class="rounded-circle admin-img-width-for-mobile"
                                                    style="width: 40px; height: 40px"
                                                    src="{{ asset('assets/images/admin.png') }}" alt="admin" />
                                                <span
                                                    class="d-block bg-success-60 border border-2 border-white rounded-circle position-absolute end-0 bottom-0"
                                                    style="width: 11px; height: 11px"></span>
                                            </div>
                                        </div>

                                        <div class="dropdown-menu border-0 bg-white dropdown-menu-end">
                                            <div class="d-flex align-items-center info">
                                                <div class="flex-shrink-0">
                                                    <img class="rounded-circle admin-img-width-for-mobile"
                                                        style="width: 40px; height: 40px"
                                                        src="{{ asset('assets/images/admin.png') }}"
                                                        alt="admin" />
                                                </div>
                                                <div class="flex-grow-1 ms-10">
                                                    <h3 class="fw-medium fs-17 mb-0">Mateo Luca</h3>
                                                    <span class="fs-15 fw-medium">Admin</span>
                                                </div>
                                            </div>
                                            <ul class="admin-link mb-0 list-unstyled">
                                                <li>
                                                    <a class="dropdown-item admin-item-link d-flex align-items-center text-body"
                                                        href="my-profile.html">
                                                        <i class="material-symbols-outlined">person</i>
                                                        <span class="ms-2">My Profile</span>
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item admin-item-link d-flex align-items-center text-body"
                                                        href="settings.html">
                                                        <i class="material-symbols-outlined">settings</i>
                                                        <span class="ms-2">Settings</span>
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item admin-item-link d-flex align-items-center text-body"
                                                        href="tickets.html">
                                                        <i class="material-symbols-outlined">info</i>
                                                        <span class="ms-2">Support</span>
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="#"
                                                        class="dropdown-item admin-item-link d-flex align-items-center text-body"
                                                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                                        <i class="material-symbols-outlined">logout</i>
                                                        <span class="ms-2">Logout</span>
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </header>
            <!-- End Header Area -->
            <main>
                @yield('content')
            </main>
            <div class="flex-grow-1"></div>

            <!-- Start Footer Area -->
            <footer class="footer-area bg-white text-center rounded-10 rounded-bottom-0">
                <p class="fs-16 text-body">
                    © <span class="text-secondary">Fila</span> is Proudly Owned by
                    <a href="https://envytheme.com/" target="_blank"
                        class="text-decoration-none text-primary">EnvyTheme</a>
                </p>
            </footer>
            <!-- End Footer Area -->
        </div>
    </div>

    <style>
        @media (max-width: 767.98px) {

            .top-selling-filter-card,
            .top-selling-filter-row {
                width: 100%;
            }

            .top-selling-filter-hint {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .top-selling-filter-form {
                width: 100%;
                flex-wrap: wrap !important;
                gap: 0.75rem !important;
            }

            .top-selling-date-field {
                width: 100%;
            }

            .top-selling-date-control,
            .top-selling-filter-input {
                width: 100%;
            }

            .top-selling-filter-actions {
                width: 100%;
                flex-wrap: nowrap !important;
            }

            .top-selling-filter-actions .btn {
                flex: 1 1 50%;
                width: 50%;
                min-width: 0;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                white-space: nowrap;
            }
        }
    </style>
    <!-- End Main Content Area -->

    <!-- Link Of JS File -->
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/sidebar-menu.js') }}"></script>
    <script src="{{ asset('assets/js/quill.min.js') }}"></script>
    <script src="{{ asset('assets/js/data-table.js') }}"></script>
    <script src="{{ asset('assets/js/prism.js') }}"></script>
    <script src="{{ asset('assets/js/clipboard.min.js') }}"></script>
    <script src="{{ asset('assets/js/simplebar.min.js') }}"></script>
    <script src="{{ asset('assets/js/apexcharts.min.js') }}"></script>
    <script src="{{ asset('assets/js/echarts.min.js') }}"></script>
    <script src="{{ asset('assets/js/swiper-bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/fullcalendar.main.js') }}"></script>
    <script src="{{ asset('assets/js/jsvectormap.min.js') }}"></script>
    <script src="{{ asset('assets/js/world-merc.js') }}"></script>
    <script src="{{ asset('assets/js/custom/apexcharts.js') }}"></script>
    <script src="{{ asset('assets/js/custom/echarts.js') }}"></script>
    <script src="{{ asset('assets/js/custom/maps.js') }}"></script>
    <script src="{{ asset('assets/js/custom/custom.js') }}"></script>
</body>

</html>
