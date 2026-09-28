@extends('layouts.app')
@section('title', 'Product Image Credits')
@section('content')
<section class="page-hero compact">
    <div class="shell">
        <span class="eyebrow">Prototype transparency</span>
        <h1>Product image credits</h1>
        <p>These are reference model photos for the demo catalog, not photos of Mobile Arena's current physical stock. Replace them with store-owned unit photos before a commercial launch, especially for pre-owned and refurbished devices.</p>
    </div>
</section>
<section class="shell credits-grid section-block">
    @php
        $credits = [
            ['iPhone 13 128GB', 'Kskhh', 'CC BY-SA 4.0', 'https://commons.wikimedia.org/wiki/File:IPhone_13.jpg'],
            ['iPhone 12 128GB', '茅野ふたば', 'CC BY-SA 4.0', 'https://commons.wikimedia.org/wiki/File:IPhone_12_Black_256g.jpg'],
            ['Galaxy A56 5G', 'Captainmorlypogi1959', 'CC BY-SA 4.0', 'https://commons.wikimedia.org/wiki/File:Samsung_Galaxy_A56_5G_2025_(2).jpg'],
            ['Galaxy S23 256GB', 'Hajoon0102', 'CC BY-SA 4.0', 'https://commons.wikimedia.org/wiki/File:Back_of_the_Samsung_Galaxy_S23.jpg'],
            ['Redmi Note Series 256GB', 'Kcx36', 'CC BY-SA 4.0', 'https://commons.wikimedia.org/wiki/File:Redmi_Note_14_-_2024-09-27_01.jpg'],
            ['Vivo V Series 256GB', 'vivo', 'Official manufacturer reference', 'https://www.vivo.com/ph/products/v50'],
            ['iPad 10th Gen 64GB', 'Wiikiipediia', 'CC BY-SA 4.0', 'https://commons.wikimedia.org/wiki/File:Ipadtenthgen.jpg'],
            ['USB-C Fast Charger', 'Dinkun Chen', 'CC BY-SA 4.0', 'https://commons.wikimedia.org/wiki/File:SAMSUNG_EP-TA800_25W_POWER_ADAPER_WHITE_(4).jpg'],
            ['10,000mAh Power Bank', 'Cybularny', 'CC0 1.0', 'https://commons.wikimedia.org/wiki/File:Powerbank_Xiaomi,_1.jpg'],
            ['Smart Watch Series', 'X-SHLIED', 'Wikimedia Commons license shown on source page', 'https://commons.wikimedia.org/wiki/File:Mi_Watch.jpg'],
        ];
    @endphp
    @foreach($credits as [$item,$author,$license,$url])
        <article class="credit-card clay-card">
            <span class="eyebrow">Reference image</span>
            <h2>{{ $item }}</h2>
            <p>Creator/source: <strong>{{ $author }}</strong></p>
            <p>{{ $license }}</p>
            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer">Open source page ↗</a>
        </article>
    @endforeach
</section>
@endsection
