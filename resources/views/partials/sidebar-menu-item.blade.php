@php
    use Illuminate\Support\Facades\Route;

    $isOpen = false;
    $isActive = false;

    if ($item->hasChildren()) {
        foreach ($item->getChildren() as $child) {
            if ($child->getRoute() && Route::has($child->getRoute()) && request()->routeIs($child->getRoute())) {
                $isOpen = true;
            }

            if ($child->hasChildren()) {
                foreach ($child->getChildren() as $grandChild) {
                    if ($grandChild->getRoute() && Route::has($grandChild->getRoute()) && request()->routeIs($grandChild->getRoute())) {
                        $isOpen = true;
                    }
                }
            }
        }
    } elseif ($item->getRoute() && Route::has($item->getRoute())) {
        $isActive = request()->routeIs($item->getRoute());
    }
@endphp

<li class="menu-item {{ $isOpen ? 'open' : '' }} {{ $isActive ? 'active' : '' }}">
    @if ($item->hasChildren())
        <a href="javascript:void(0);" class="menu-link menu-toggle">
            @if ($item->getIcon())
                <span class="material-symbols-outlined menu-icon">{{ $item->getIcon() }}</span>
            @endif
            <span class="title">{{ $item->getTitle() }}</span>
        </a>
        <ul class="menu-sub">
            @foreach ($item->getChildren() as $child)
                @if ($child->hasChildren())
                    @php
                        $childOpen = false;
                        foreach ($child->getChildren() as $grandChild) {
                            if ($grandChild->getRoute() && Route::has($grandChild->getRoute()) && request()->routeIs($grandChild->getRoute())) {
                                $childOpen = true;
                            }
                        }
                    @endphp
                    <li class="menu-item {{ $childOpen ? 'open' : '' }}">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <span class="title">{{ $child->getTitle() }}</span>
                        </a>
                        <ul class="menu-sub">
                            @foreach ($child->getChildren() as $grandChild)
                                @if ($grandChild->getRoute() && Route::has($grandChild->getRoute()))
                                    <li class="menu-item {{ request()->routeIs($grandChild->getRoute()) ? 'active' : '' }}">
                                        <a href="{{ route($grandChild->getRoute()) }}"
                                            class="menu-link {{ request()->routeIs($grandChild->getRoute()) ? 'active' : '' }}">
                                            {{ $grandChild->getTitle() }}
                                        </a>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </li>
                @elseif ($child->getRoute() && Route::has($child->getRoute()))
                    <li class="menu-item {{ request()->routeIs($child->getRoute()) ? 'active' : '' }}">
                        <a href="{{ route($child->getRoute()) }}"
                            class="menu-link {{ request()->routeIs($child->getRoute()) ? 'active' : '' }}">
                            {{ $child->getTitle() }}
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
    @elseif ($item->getRoute() && Route::has($item->getRoute()))
        <a href="{{ route($item->getRoute()) }}" class="menu-link {{ $isActive ? 'active' : '' }}">
            @if ($item->getIcon())
                <span class="material-symbols-outlined menu-icon">{{ $item->getIcon() }}</span>
            @endif
            <span class="title">{{ $item->getTitle() }}</span>
        </a>
    @endif
</li>
