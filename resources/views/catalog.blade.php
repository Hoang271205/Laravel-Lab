<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CoffeeShop — Menu</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f6f1e8; color: #30251e; font: 16px/1.6 system-ui, sans-serif; }
        main { max-width: 1100px; margin: auto; padding: 48px 24px; }
        header { border-bottom: 1px solid #d8c9b7; padding-bottom: 24px; }
        h1 { font: 700 clamp(36px, 6vw, 64px)/1.1 Georgia, serif; margin: 10px 0; }
        h2 { font: 700 30px Georgia, serif; }
        .eyebrow { text-transform: uppercase; letter-spacing: .15em; color: #72553c; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; }
        article { padding: 24px; border: 1px solid #decfbe; border-radius: 16px; background: #fffaf3; }
        article h3 { margin-top: 0; }
        .price { color: #72553c; font-weight: 700; }
        footer { margin-top: 40px; color: #72553c; }
    </style>
</head>
<body>
<main>
    <header>
        <span class="eyebrow">CoffeeShop · Menu</span>
        <h1>Coffee & a little more.</h1>
        <p>Danh sách sản phẩm theo danh mục, đọc trực tiếp từ database.</p>
    </header>
    @forelse ($categories as $category)
        <section aria-labelledby="category-{{ $category->id }}">
            <h2 id="category-{{ $category->id }}">{{ $category->name }} <small>({{ $category->products->count() }})</small></h2>
            <div class="grid">
                @forelse ($category->products as $product)
                    <article>
                        <h3>{{ $product->name }}</h3>
                        <p>{{ $product->description }}</p>
                        <p class="price">{{ $product->formatted_price }}</p>
                    </article>
                @empty
                    <p>Danh mục này chưa có sản phẩm.</p>
                @endforelse
            </div>
        </section>
    @empty
        <p>Chưa có dữ liệu menu.</p>
    @endforelse
    <footer>Laravel Database · Section 1</footer>
</main>
</body>
</html>
