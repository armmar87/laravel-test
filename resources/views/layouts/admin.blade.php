<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>@yield('title', 'Admin Panel') — Book Reservation System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

    {{-- ── Navigation ── --}}
    <nav class="bg-indigo-700 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
            <a href="{{ route('admin.web.reservations.index') }}"
               class="text-xl font-bold tracking-tight">
                📚 Book Reservation — Admin
            </a>
            <div class="flex items-center gap-4 text-sm">
                <span class="opacity-80">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('admin.web.logout') }}">
                    @csrf
                    <button type="submit"
                            class="bg-indigo-500 hover:bg-indigo-400 transition px-4 py-1.5 rounded font-medium">
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </nav>

    {{-- ── Flash messages ── --}}
    <div class="max-w-7xl mx-auto px-6 mt-4">
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-800 rounded px-4 py-3 mb-4 flex items-center gap-2">
                <span>✅</span> {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-800 rounded px-4 py-3 mb-4 flex items-center gap-2">
                <span>❌</span> {{ session('error') }}
            </div>
        @endif
    </div>

    {{-- ── Page content ── --}}
    <main class="max-w-7xl mx-auto px-6 pb-12">
        @yield('content')
    </main>

</body>
</html>

