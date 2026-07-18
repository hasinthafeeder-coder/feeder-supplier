@extends('layout_main.app')

@section('content')
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
                                    <button class="header-burger-menu bg-transparent p-0 border-0 position-relative top-3"
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
                                <li class="header-right-item language-item">
                                    <div class="dropdown notifications language">
                                        <button class="btn btn-secondary dropdown-toggle border-0 p-0 position-relative"
                                            type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <span class="material-symbols-outlined" style="font-size: 19px">translate</span>
                                        </button>
                                        <div class="dropdown-menu dropdown-lg p-0 border-0 dropdown-menu-end">
                                            <span class="fw-medium fs-16 text-secondary d-block title"
                                                style="padding-top: 20px; padding-bottom: 20px">Choose Language</span>
                                            <div class="max-h-275" data-simplebar>
                                                <div class="notification-menu">
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0">
                                                                <img src="{{ asset('assets/images/usa.png') }}"
                                                                    class="wh-30 rounded-circle" alt="usa" />
                                                            </div>
                                                            <div class="flex-grow-1 ms-10">
                                                                <span class="text-secondary fw-medium fs-15">English</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                                <div class="notification-menu">
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0">
                                                                <img src="{{ asset('assets/images/australia.png') }}"
                                                                    class="wh-30 rounded-circle" alt="australia" />
                                                            </div>
                                                            <div class="flex-grow-1 ms-10">
                                                                <span
                                                                    class="text-secondary fw-medium fs-15">Australia</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                                <div class="notification-menu">
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0">
                                                                <img src="{{ asset('assets/images/spain.png') }}"
                                                                    class="wh-30 rounded-circle" alt="spain" />
                                                            </div>
                                                            <div class="flex-grow-1 ms-10">
                                                                <span class="text-secondary fw-medium fs-15">Spanish</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                                <div class="notification-menu">
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0">
                                                                <img src="{{ asset('assets/images/france.png') }}"
                                                                    class="wh-30 rounded-circle" alt="portugal" />
                                                            </div>
                                                            <div class="flex-grow-1 ms-10">
                                                                <span class="text-secondary fw-medium fs-15">France</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                                <div class="notification-menu mb-0">
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0">
                                                                <img src="{{ asset('assets/images/germany.png') }}"
                                                                    class="wh-30 rounded-circle" alt="Germany" />
                                                            </div>
                                                            <div class="flex-grow-1 ms-10">
                                                                <span class="text-secondary fw-medium fs-15">Spain</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <li class="header-right-item light-dark-item">
                                    <div class="light-dark">
                                        <button class="switch-toggle dark-btn p-0 bg-transparent lh-0 border-0"
                                            id="switch-toggle">
                                            <span class="dark"><i class="material-symbols-outlined">dark_mode</i></span>
                                            <span class="light"><i class="material-symbols-outlined">light_mode</i></span>
                                        </button>
                                    </div>
                                </li>
                                <li class="header-right-item calendar-item">
                                    <div class="dropdown notifications">
                                        <a href="calendar.html" class="btn btn-secondary border-0 p-0 position-relative">
                                            <span class="material-symbols-outlined"
                                                style="font-size: 19px">calendar_today</span>
                                        </a>
                                    </div>
                                </li>
                                <li class="header-right-item messages-item">
                                    <div class="dropdown notifications noti messages">
                                        <button class="btn btn-secondary border-0 p-0 position-relative" type="button"
                                            data-bs-toggle="dropdown" aria-expanded="false">
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
                                                                    style="width: 44px; height: 44px" alt="images" />
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
                                                                    style="width: 44px; height: 44px" alt="images" />
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
                                                                    style="width: 44px; height: 44px" alt="images" />
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
                                        <button class="btn btn-secondary border-0 p-0 position-relative" type="button"
                                            data-bs-toggle="dropdown" aria-expanded="false">
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
                                                                <i class="material-symbols-outlined text-primary">sms</i>
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
                                                                <i class="material-symbols-outlined text-info">person</i>
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
                                                        src="{{ asset('assets/images/admin.png') }}" alt="admin" />
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
                                                    <a class="dropdown-item admin-item-link d-flex align-items-center text-body"
                                                        href="logout.html">
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

            {{-- Orders Overview --}}
            <div class="main-content-container overflow-hidden">
                <div class="row">
                    {{-- Start Orders Overview --}}
                    <div class="col-xxl-12 col-xxxxxl-12">
                        <div class="card bg-white p-40 rounded-10 border-0 mb-4 position-relative z-1 quick-view-bg"
                            style="padding-top: 29px;">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-20">
                                <h3 class="text-white fs-26">Orders Overview</h3>

                                <div class="dropdown action-opt text-center">
                                    <button class="btn bg-transparent p-0" type="button" data-bs-toggle="dropdown"
                                        aria-expanded="false">
                                        <i class="material-symbols-outlined fs-20 text-white">more_vert</i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end bg-white border-0 box-shadow">
                                        <li>
                                            <a class="dropdown-item" href="javascript:;">
                                                Last Day
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="javascript:;">
                                                Last Week
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="javascript:;">
                                                Last Month
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="javascript:;">
                                                Last Year
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card bg-white rounded-10 border-0"
                                style="box-shadow: 0px 0px 10px 3px rgba(195, 195, 195, 0.5);">
                                <div class="row g-0">
                                    <div
                                        class="col-sm-4 col-xxl-4 col-xxxl-4 border-bottom border-end border-border-color-90">
                                        <div
                                            class="card bg-white p-40 rounded-10 border border-white mb-0 position-relative z-1">
                                            <div class="d-flex">
                                                <div class="flex-grow-1">
                                                    <h3 class="mb-10 lh-1 fs-14 text-body fw-medium">Pending</h3>
                                                    <h2 class="fs-26 fw-bold mb-10 lh-1">2554</h2>
                                                </div>
                                                <div class="flex-shrink-0 ms-3 position-relative" style="width: 64px;">
                                                    <div class="w-100 position-absolute top-50 translate-middle-y">
                                                        <i class="ri-time-line d-flex justify-content-center align-items-center fs-36 rounded-1"
                                                            style="width: 70px; height: 70px; color: #EF4923; background-color: rgba(239, 73, 35, 0.14);"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="col-sm-4 col-xxl-4 col-xxxl-4 border-bottom border-start border-end border-border-color-90">
                                        <div
                                            class="card bg-white p-40 rounded-10 border border-white mb-0 position-relative z-1">
                                            <div class="d-flex">
                                                <div class="flex-grow-1">
                                                    <h3 class="mb-10 lh-1 fs-14 text-body fw-medium">Out Orders</h3>
                                                    <h2 class="fs-26 fw-bold mb-10 lh-1">5517</h2>
                                                </div>
                                                <div class="flex-shrink-0 ms-3 position-relative" style="width: 64px;">
                                                    <div class="w-100 position-absolute top-50 translate-middle-y">
                                                        <i class="ri-shopping-cart-2-line d-flex justify-content-center align-items-center fs-36 rounded-1"
                                                            style="width: 70px; height: 70px; color: #EF4923; background-color: rgba(239, 73, 35, 0.14);"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="col-sm-4 col-xxl-4 col-xxxl-4 border-bottom border-start border-border-color-90">
                                        <div
                                            class="card bg-white p-40 rounded-10 border border-white mb-0 position-relative z-1">
                                            <div class="d-flex">
                                                <div class="flex-grow-1">
                                                    <h3 class="mb-10 lh-1 fs-14 text-body fw-medium">Printing
                                                    </h3>
                                                    <h2 class="fs-26 fw-bold mb-10 lh-1">5466</h2>
                                                </div>
                                                <div class="flex-shrink-0 ms-3 position-relative" style="width: 64px;">
                                                    <div class="w-100 position-absolute top-50 translate-middle-y">
                                                        <i class="ri-printer-fill d-flex justify-content-center align-items-center fs-36 rounded-1"
                                                            style="width: 70px; height: 70px; color: #EF4923; background-color: rgba(239, 73, 35, 0.14);"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="col-sm-4 col-xxl-4 col-xxxl-4 border-top border-end border-border-color-90">
                                        <div
                                            class="card bg-white p-40 rounded-10 border border-white mb-0 position-relative z-1">
                                            <div class="d-flex">
                                                <div class="flex-grow-1">
                                                    <h3 class="mb-10 lh-1 fs-14 text-body fw-medium">Packaging</h3>
                                                    <h2 class="fs-26 fw-bold mb-10 lh-1">1533</h2>
                                                </div>
                                                <div class="flex-shrink-0 ms-3 position-relative" style="width: 64px;">
                                                    <div class="w-100 position-absolute top-50 translate-middle-y">
                                                        <i class="ri-archive-stack-fill d-flex justify-content-center align-items-center fs-36 rounded-1"
                                                            style="width: 70px; height: 70px; color: #EF4923; background-color: rgba(239, 73, 35, 0.14);"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="col-sm-4 col-xxl-4 col-xxxl-4 border-top border-end border-start border-border-color-90">
                                        <div
                                            class="card bg-white p-40 rounded-10 border border-white mb-0 position-relative z-1">
                                            <div class="d-flex">
                                                <div class="flex-grow-1">
                                                    <h3 class="mb-10 lh-1 fs-14 text-body fw-medium">Dispatch
                                                    </h3>
                                                    <h2 class="fs-26 fw-bold mb-10 lh-1">212</h2>
                                                </div>
                                                <div class="flex-shrink-0 ms-3 position-relative" style="width: 64px;">
                                                    <div class="w-100 position-absolute top-50 translate-middle-y">
                                                        <i class="ri-truck-line d-flex justify-content-center align-items-center fs-36 rounded-1"
                                                            style="width: 70px; height: 70px; color: #EF4923; background-color: rgba(239, 73, 35, 0.14);"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="col-sm-4 col-xxl-4 col-xxxl-4 border-top border-start border-border-color-90">
                                        <div
                                            class="card bg-white p-40 rounded-10 border border-white mb-0 position-relative z-1">
                                            <div class="d-flex">
                                                <div class="flex-grow-1">
                                                    <h3 class="mb-10 lh-1 fs-14 text-body fw-medium">Out of Stock</h3>
                                                    <h2 class="fs-26 fw-bold mb-10 lh-1">1533</h2>
                                                </div>
                                                <div class="flex-shrink-0 ms-3 position-relative" style="width: 64px;">
                                                    <div class="w-100 position-absolute top-50 translate-middle-y">
                                                        <i class="ri-close-circle-line d-flex justify-content-center align-items-center fs-36 rounded-1"
                                                            style="width: 70px; height: 70px; color: #FDE5E0; background-color: #EF4923;"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- End Orders Overview --}}

                    <div class="col-lg-6">
                        <div class="card bg-white p-20 rounded-10 border border-white mb-4">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <h3>Total Sales</h3>

                                <div class="dropdown select-dropdown without-border">
                                    <button class="dropdown-toggle bg-transparent text-secondary fs-15"
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                        Year 2025
                                    </button>

                                    <ul class="dropdown-menu dropdown-menu-end bg-white border-0 box-shadow rounded-10"
                                        data-simplebar>
                                        <li>
                                            <button class="dropdown-item text-secondary">
                                                Year 2025
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item text-secondary">
                                                Year 2025
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item text-secondary">
                                                Year 2023
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div id="total_sales_chart" style="margin-bottom: -16px; margin-top: -1.5px"></div>
                        </div>
                    </div>

                    <div class="col-lg-6 col-xxl-3 col-xxxl-6">
                        <div class="row">

                            <div class="col-md-6 col-lg-12">
                                <div class="card bg-white p-20 rounded-10 border border-white mb-4">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <h3 class="mb-10">Today Total Sale</h3>
                                            <h2 class="fs-26 fw-medium mb-0 lh-1">84,127</h2>
                                        </div>
                                        <div class="flex-shrink-0 ms-3">
                                            <div class="text-center rounded-circle d-block"
                                                style="width: 75px; height: 75px; line-height: 105px; background-color: #EF4923;">
                                                <i class="material-symbols-outlined fs-40"
                                                    style="color: #FFFFFF;">point_of_sale</i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center"
                                        style="margin-top: 21px">
                                        <p class="mb-0 fs-14">
                                            Total sales decreased by 1.25%
                                        </p>
                                        <span
                                            class="d-flex align-content-center gap-1 bg-danger bg-opacity-10 border border-danger"
                                            style="padding: 3px 5px">
                                            <i class="material-symbols-outlined fs-14 text-danger">trending_down</i>
                                            <span class="lh-1 fs-14 text-danger">1.25%</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-12">
                                <div class="card bg-white p-20 rounded-10 border border-white mb-4">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <h3 class="mb-10">Money Recieved</h3>
                                            <h2 class="fs-26 fw-medium mb-0 lh-1">20,705</h2>
                                        </div>
                                        <div class="flex-shrink-0 ms-3">
                                            <div class="text-center rounded-circle d-block"
                                                style="width: 75px; height: 75px; line-height: 105px; background-color: #EF4923;">
                                                <i class="material-symbols-outlined fs-40"
                                                    style="color: #FFFFFF;">payments</i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center"
                                        style="margin-top: 21px">
                                        <p class="mb-0 fs-15">
                                            475 Orders
                                        </p>
                                        <span
                                            class="d-flex align-content-center gap-1 bg-primary bg-opacity-10 border border-primary"
                                            style="padding: 3px 5px">
                                            <i class="material-symbols-outlined fs-14 text-primary">trending_up</i>
                                            <span class="lh-1 fs-14 text-primary">4.75%</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-12 col-xxl-3 col-xxxl-12">
                        <div class="row">
                            <div class="col-md-6 col-xxxl-6 col-xxl-12">
                                <div class="card bg-white p-20 rounded-10 border border-white mb-4">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <h3 class="mb-10">Paid Orders Count</h3>
                                            <h2 class="fs-26 fw-medium mb-0 lh-1">278</h2>
                                        </div>
                                        <div class="flex-shrink-0 ms-3">
                                            <div class="text-center rounded-circle d-block"
                                                style="width: 75px; height: 75px; line-height: 116px; background-color: #EF4923;">
                                                <i class="material-symbols-outlined fs-50"
                                                    style="color: #FFFFFF;">paid</i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center"
                                        style="margin-top: 23px">
                                        <p class="mb-0 fs-14">Revenue increases this month</p>
                                        <span
                                            class="d-flex align-content-center gap-1 bg-primary bg-opacity-10 border border-primary"
                                            style="padding: 3px 5px">
                                            <i class="material-symbols-outlined fs-14 text-primary">trending_up</i>
                                            <span class="lh-1 fs-14 text-primary">3.15%</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xxxl-6 col-xxl-12">
                                <div class="card bg-white p-20 rounded-10 border border-white mb-4">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <h3 class="mb-10">Paid Orders this month</h3>
                                            <h2 class="fs-26 fw-medium mb-0 lh-1">15,278</h2>
                                        </div>
                                        <div class="flex-shrink-0 ms-3">
                                            <div class="text-center rounded-circle d-block"
                                                style="width: 75px; height: 75px; line-height: 116px; background-color: #EF4923;">
                                                <i class="material-symbols-outlined fs-50"
                                                    style="color: #FFFFFF;">price_check</i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center"
                                        style="margin-top: 23px">
                                        <p class="mb-0 fs-14">Revenue increases this month</p>
                                        <span
                                            class="d-flex align-content-center gap-1 bg-primary bg-opacity-10 border border-primary"
                                            style="padding: 3px 5px">
                                            <i class="material-symbols-outlined fs-14 text-primary">trending_up</i>
                                            <span class="lh-1 fs-14 text-primary">3.15%</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xxl-6 col-xxxl-12">
                        <div class="card bg-white p-20 rounded-10 border border-white mb-4">
                            <div
                                class="d-flex justify-content-between align-items-center flex-wrap flex-md-nowrap gap-3 mb-20">
                                <h3 class="text-nowrap">Top Selling Products</h3>

                                <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center gap-2 justify-content-start justify-content-md-end top-products-filter-controls"
                                    style="width: 100%; flex: 1 1 auto;">
                                    <style>
                                        @media (max-width: 767px) {
                                            input[type="date"].form-control {
                                                width: 100% !important;
                                            }
                                        }

                                        @media (min-width: 768px) {
                                            input[type="date"].form-control {
                                                width: 160px;
                                            }

                                            input[type="text"].form-control {
                                                width: 220px;
                                            }
                                        }
                                    </style>
                                    <form class="table-src-form position-relative">

                                    </form>
                                    <input type="text" class="form-control form-control-sm"
                                        placeholder="Search products..." />
                                    <div
                                        class="src-btn position-absolute top-50 start-0 translate-middle-y bg-transparent p-0 border-0">
                                        <span class="material-symbols-outlined">search</span>
                                    </div>
                                    <input type="date" class="form-control form-control-sm"
                                        aria-label="Top selling products start date" style="min-width: 140px;" />
                                    <input type="date" class="form-control form-control-sm"
                                        aria-label="Top selling products end date" style="min-width: 140px;" />
                                    <button type="button"
                                        class="btn btn-primary text-white btn-sm top-products-filter-btn"
                                        style="min-width: 88px;">Filter</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm top-products-reset-btn"
                                        style="min-width: 88px;">Reset</button>
                                </div>
                            </div>

                            <div class="default-table-area without-header table-top-selling-products">
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <tbody>
                                            <tr>
                                                <td class="text-body fw-medium text-nowrap"
                                                    style="padding-right: 0.35rem; width: 40px;">01.</td>
                                                <td class="text-start" style="padding-left: 0.35rem;">
                                                    <a href="product-details.html"
                                                        class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/product1.png') }}"
                                                                class="rounded-circle" style="width: 50px; height: 50px"
                                                                alt="product1" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-normal hover-text">
                                                                Smart Watch
                                                            </h3>
                                                            <span class="fs-14 text-body fw-normal">953 Items
                                                                Sold</span>
                                                        </div>
                                                    </a>
                                                </td>
                                                <td class="text-body">$90,954</td>
                                            </tr>
                                            <tr>
                                                <td class="text-body fw-medium text-nowrap"
                                                    style="padding-right: 0.35rem;">02.</td>
                                                <td class="text-start" style="padding-left: 0.35rem;">
                                                    <a href="product-details.html"
                                                        class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/product2.png') }}"
                                                                class="rounded-circle" style="width: 50px; height: 50px"
                                                                alt="product2" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-normal hover-text">
                                                                Mobile Phone
                                                            </h3>
                                                            <span class="fs-14 text-body fw-normal">876 Items
                                                                Sold</span>
                                                        </div>
                                                    </a>
                                                </td>
                                                <td class="text-body">$85,648</td>
                                            </tr>
                                            <tr>
                                                <td class="text-body fw-medium text-nowrap"
                                                    style="padding-right: 0.35rem;">03.</td>
                                                <td class="text-start" style="padding-left: 0.35rem;">
                                                    <a href="product-details.html"
                                                        class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/product3.png') }}"
                                                                class="rounded-circle" style="width: 50px; height: 50px"
                                                                alt="product3" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-normal hover-text">
                                                                Laptop Device
                                                            </h3>
                                                            <span class="fs-14 text-body fw-normal">823 Items
                                                                Sold</span>
                                                        </div>
                                                    </a>
                                                </td>
                                                <td class="text-body">$79,852</td>
                                            </tr>
                                            <tr>
                                                <td class="text-body fw-medium text-nowrap"
                                                    style="padding-right: 0.35rem;">04.</td>
                                                <td class="text-start" style="padding-left: 0.35rem;">
                                                    <a href="product-details.html"
                                                        class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/product4.png') }}"
                                                                class="rounded-circle" style="width: 50px; height: 50px"
                                                                alt="product4" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-normal hover-text">
                                                                Black T-Shirt
                                                            </h3>
                                                            <span class="fs-14 text-body fw-normal">743 Items
                                                                Sold</span>
                                                        </div>
                                                    </a>
                                                </td>
                                                <td class="text-body">$73,624</td>
                                            </tr>
                                            <tr>
                                                <td class="text-body fw-medium text-nowrap"
                                                    style="padding-right: 0.35rem;">05.</td>
                                                <td class="text-start" style="padding-left: 0.35rem;">
                                                    <a href="product-details.html"
                                                        class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/product5.png') }}"
                                                                class="rounded-circle" style="width: 50px; height: 50px"
                                                                alt="product5" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-normal hover-text">Headphones</h3>
                                                            <span class="fs-14 text-body fw-normal">693 Items
                                                                Sold</span>
                                                        </div>
                                                    </a>
                                                </td>
                                                <td class="text-body">$65,973</td>
                                            </tr>
                                            <tr>
                                                <td class="text-body fw-medium text-nowrap"
                                                    style="padding-right: 0.35rem;">06.</td>
                                                <td class="text-start" style="padding-left: 0.35rem;">
                                                    <a href="product-details.html"
                                                        class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/product6.png') }}"
                                                                class="rounded-circle" style="width: 50px; height: 50px"
                                                                alt="product6" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-normal hover-text">Hand Watch</h3>
                                                            <span class="fs-14 text-body fw-normal">654 Items
                                                                Sold</span>
                                                        </div>
                                                    </a>
                                                </td>
                                                <td class="text-body">$42,455</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div
                                    class="d-flex justify-content-center justify-content-sm-between align-items-center text-center flex-wrap gap-2 showing-wrap pt-15">
                                    <span class="fs-15">Showing 1 to 5 of 50 entries</span>

                                    <nav class="custom-pagination" aria-label="Page navigation example">
                                        <ul class="pagination mb-0 justify-content-center">
                                            <li class="page-item">
                                                <button class="page-link icon" aria-label="Previous">
                                                    <i class="material-symbols-outlined">west</i>
                                                </button>
                                            </li>
                                            <li class="page-item">
                                                <button class="page-link active">1</button>
                                            </li>
                                            <li class="page-item">
                                                <button class="page-link">2</button>
                                            </li>
                                            <li class="page-item">
                                                <button class="page-link">3</button>
                                            </li>
                                            <li class="page-item">
                                                <button class="page-link icon" aria-label="Next">
                                                    <i class="material-symbols-outlined">east</i>
                                                </button>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xxl-8 col-xxxxl-12">
                        <div class="card bg-white rounded-10 border border-white mb-4">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-20">
                                <h3>Couriers</h3>

                                <div class="d-flex align-items-center">

                                    <form class="table-src-form position-relative">
                                        <input type="text" class="form-control" placeholder="Search here..." />
                                        <div
                                            class="src-btn position-absolute top-50 start-0 translate-middle-y bg-transparent p-0 border-0">
                                            <span class="material-symbols-outlined">search</span>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="default-table-area mx-minus-1 table-recent-orders">
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <thead>
                                            <tr>
                                                <th scope="col" class="fw-medium text-nowrap">Name</th>
                                                <th scope="col" class="fw-medium text-nowrap">Dispatch</th>
                                                <th scope="col" class="fw-medium text-nowrap">Delivery</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <a href="javascript:void(0);"
                                                        class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/user1.jpg') }}"
                                                                class="rounded-circle" style="width: 50px; height: 50px"
                                                                alt="dhl" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-medium hover-text mb-0 fs-16">DHL Express</h3>
                                                        </div>
                                                    </a>
                                                </td>
                                                <td class="text-body">40</td>
                                                <td class="text-body">85%</td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <a href="javascript:void(0);"
                                                        class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/user2.jpg') }}"
                                                                class="rounded-circle" style="width: 50px; height: 50px"
                                                                alt="fedex" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-medium hover-text mb-0 fs-16">FedEx</h3>
                                                        </div>
                                                    </a>
                                                </td>
                                                <td class="text-body">36</td>
                                                <td class="text-body">86%</td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <a href="javascript:void(0);"
                                                        class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/user3.jpg') }}"
                                                                class="rounded-circle" style="width: 50px; height: 50px"
                                                                alt="ups" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-medium hover-text mb-0 fs-16">UPS</h3>
                                                        </div>
                                                    </a>
                                                </td>
                                                <td class="text-body">33</td>
                                                <td class="text-body">88%</td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <a href="javascript:void(0);"
                                                        class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/user4.jpg') }}"
                                                                class="rounded-circle" style="width: 50px; height: 50px"
                                                                alt="usps" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-medium hover-text mb-0 fs-16">USPS</h3>
                                                        </div>
                                                    </a>
                                                </td>
                                                <td class="text-body">30</td>
                                                <td class="text-body">87%</td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <a href="javascript:void(0);"
                                                        class="d-flex align-items-center text-decoration-none">
                                                        <div class="flex-shrink-0">
                                                            <img src="{{ asset('assets/images/user5.jpg') }}"
                                                                class="rounded-circle" style="width: 50px; height: 50px"
                                                                alt="aramex" />
                                                        </div>
                                                        <div class="flex-grow-1 ms-12">
                                                            <h3 class="fw-medium hover-text mb-0 fs-16">Aramex</h3>
                                                        </div>
                                                    </a>
                                                </td>
                                                <td class="text-body">27</td>
                                                <td class="text-body">89%</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div
                                    class="d-flex justify-content-center justify-content-sm-between align-items-center text-center flex-wrap gap-2 showing-wrap pt-15 p-20">
                                    <span class="fs-15">Showing 1 to 5 of 50 entries</span>

                                    <nav class="custom-pagination" aria-label="Page navigation example">
                                        <ul class="pagination mb-0 justify-content-center">
                                            <li class="page-item">
                                                <button class="page-link icon" aria-label="Previous">
                                                    <i class="material-symbols-outlined">west</i>
                                                </button>
                                            </li>
                                            <li class="page-item">
                                                <button class="page-link active">1</button>
                                            </li>
                                            <li class="page-item">
                                                <button class="page-link">2</button>
                                            </li>
                                            <li class="page-item">
                                                <button class="page-link">3</button>
                                            </li>
                                            <li class="page-item">
                                                <button class="page-link icon" aria-label="Next">
                                                    <i class="material-symbols-outlined">east</i>
                                                </button>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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
        <style>@media (max-width: 767.98px) {

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
@endsection
