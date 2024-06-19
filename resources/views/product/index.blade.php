<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Laravel</title>

</head>

<body class="font-sans antialiased dark:bg-black dark:text-white/50">
    <div style="display: flex; gap: 3rem">
        @foreach ($products as $product)
            <div style="flex: 1">
                <img src="{{ $product->image }}" alt="" style="max-width: 100%">
                <h5>{{ $product->name }}</h5>
                <p>{{ $product->price }}</p>
            </div>
        @endforeach
    </div>
    <p>
    <form action="{{ route('checkout') }}" method="post">
        @csrf
        <button>checkout</button>
    </form>
    </p>
</body>

</html>
