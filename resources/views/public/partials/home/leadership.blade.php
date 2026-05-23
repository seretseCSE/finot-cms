{{-- ═══════════════════════════════════════════════════════
     4.5 OUR LEADERSHIP — PREMIUM CENTERED LAYOUT
     3 → 4 → 3 STRUCTURE
═══════════════════════════════════════════════════════ --}}
<section id="leadership" style="
    padding:120px 24px;
    background:var(--dark-900);
    position:relative;
    overflow:hidden;
">

    {{-- Background Overlay --}}
    <div class="tilet" style="
        position:absolute;
        inset:0;
        opacity:.2;
    "></div>

    {{-- Blue Glow --}}
    <div style="
        position:absolute;
        top:20%;
        right:10%;
        width:420px;
        height:420px;
        border-radius:50%;
        background:var(--blue-primary);
        filter:blur(160px);
        opacity:.06;
        pointer-events:none;
    "></div>

    {{-- Gold Glow --}}
    <div style="
        position:absolute;
        bottom:-120px;
        left:-120px;
        width:340px;
        height:340px;
        border-radius:50%;
        background:var(--gold);
        filter:blur(140px);
        opacity:.04;
        pointer-events:none;
    "></div>

    <div style="
        max-width:1280px;
        margin:0 auto;
        position:relative;
        z-index:2;
    ">

        {{-- Section Header --}}
        <div style="
            text-align:center;
            margin-bottom:80px;
        ">
            <div class="sec-label sr" style="justify-content:center;">
                {{ __('Leadership') }}
            </div>

            <h2 class="display sr" style="
                font-size:clamp(2rem,4vw,3.4rem);
                margin-bottom:16px;
                line-height:1.1;
            ">
                <span class="am">የሰንበት ትምህርት ቤቱ አመራሮች</span>
                —
                {{ __('Our Leadership') }}
            </h2>

            <p class="sr" style="
                color:var(--parchment-60);
                max-width:720px;
                margin:0 auto;
                font-size:1.02rem;
                line-height:1.8;
            ">
                {{ __('Dedicated servants leading our Sunday school with faith, humility, and spiritual responsibility.') }}
            </p>
        </div>

        {{-- TOP LEADERS --}}
        <div style="
            display:flex;
            justify-content:center;
            gap:40px;
            flex-wrap:wrap;
            margin-bottom:90px;
        ">

            @foreach([
                [
                    'name' => 'Melake Hayil Kesis Solomon Mulugeta',
                    'am' => 'መልአከ ኃይል ቀሲስ ሰሎሞን ሙሉጌታ',
                    'title' => __('President'),
                    'title_am' => 'ሰብሳቢ',
                    'icon' => 'faith',
                    'color' => '#1A44F7'
                ],
                [
                    'name' => 'Deacon Yosef Tefera',
                    'am' => 'ዲያቆን ዮሴፍ ተፈራ',
                    'title' => __('Vice President'),
                    'title_am' => 'ምክትል ሰብሳቢ',
                    'icon' => 'education',
                    'color' => '#F3BA15'
                ],
                [
                    'name' => 'Sister Hiwot Abera',
                    'am' => 'እህት ሕይወት አበራ',
                    'title' => __('General Secretary'),
                    'title_am' => 'ዋና ጸሐፊ',
                    'icon' => 'leadership',
                    'color' => '#10B981'
                ],
            ] as $leader)

            <div class="sr" style="
                width:300px;
                text-align:center;
            ">

                {{-- Card --}}
                <div class="card" style="
                    padding:42px 28px;
                    border-radius:28px;
                    background:
                        linear-gradient(
                            180deg,
                            rgba(255,255,255,.03),
                            rgba(255,255,255,.01)
                        );
                    backdrop-filter:blur(20px);
                    border:1px solid rgba(255,255,255,.08);
                    transition:all .4s cubic-bezier(.22,1,.36,1);
                ">

                    {{-- Avatar --}}
                    <div style="
                        width:180px;
                        height:180px;
                        border-radius:50%;
                        margin:0 auto 28px;
                        position:relative;
                        padding:8px;
                        background:var(--glass);
                        border:2px dashed var(--gold-border);
                    ">

                        <div style="
                            width:100%;
                            height:100%;
                            border-radius:50%;
                            background:
                                linear-gradient(
                                    135deg,
                                    {{ $leader['color'] }}22,
                                    {{ $leader['color'] }}55
                                );
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            border:1px solid {{ $leader['color'] }}44;
                            box-shadow:
                                0 20px 50px rgba(0,0,0,.25),
                                0 0 40px {{ $leader['color'] }}22;
                        ">
                            <x-tour-icon
                                :name="$leader['icon']"
                                size="42"
                                style="color:{{ $leader['color'] }}"
                            />
                        </div>

                        {{-- Floating Badge --}}
                        <div style="
                            position:absolute;
                            bottom:6px;
                            right:6px;
                            width:42px;
                            height:42px;
                            border-radius:50%;
                            background:var(--gold);
                            color:var(--bg-950);
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            box-shadow:
                                0 10px 24px rgba(243,186,21,.35);
                            border:3px solid var(--bg-950);
                        ">
                            ★
                        </div>
                    </div>

                    {{-- Title --}}
                    <div style="
                        font-size:.72rem;
                        color:var(--gold);
                        font-weight:700;
                        text-transform:uppercase;
                        letter-spacing:.18em;
                        margin-bottom:10px;
                    ">
                        {{ $leader['title'] }}
                    </div>

                    {{-- Amharic --}}
                    <h3 class="am" style="
                        font-size:1.18rem;
                        font-weight:700;
                        color:var(--text-display);
                        margin-bottom:8px;
                        line-height:1.4;
                    ">
                        {{ $leader['am'] }}
                    </h3>

                    {{-- English --}}
                    <div style="
                        font-size:.92rem;
                        color:var(--text-60);
                        line-height:1.7;
                    ">
                        {{ $leader['name'] }}
                    </div>

                </div>
            </div>

            @endforeach
        </div>

        {{-- DEPARTMENT HEADS --}}
        <div style="
            display:flex;
            flex-wrap:wrap;
            justify-content:center;
            gap:36px;
            max-width:1200px;
            margin:0 auto;
        ">

            @foreach($departments as $index => $department)

                <div class="sr" style="
                    width:260px;
                    flex-shrink:0;
                ">

                    <div class="card" style="
                        text-align:center;
                        padding:34px 24px;
                        border-radius:24px;
                        height:100%;
                        background:
                            linear-gradient(
                                180deg,
                                rgba(255,255,255,.025),
                                rgba(255,255,255,.01)
                            );
                        backdrop-filter:blur(18px);
                        border:1px solid rgba(255,255,255,.07);
                        transition:all .4s cubic-bezier(.22,1,.36,1);
                    ">

                        {{-- Avatar --}}
                        <div style="
                            width:120px;
                            height:120px;
                            border-radius:50%;
                            margin:0 auto 24px;
                            position:relative;
                            padding:6px;
                            background:var(--glass);
                            border:2px dashed var(--gold-border);
                        ">

                            <div style="
                                width:100%;
                                height:100%;
                                border-radius:50%;
                                background:
                                    linear-gradient(
                                        135deg,
                                        #1A44F722,
                                        #1A44F755
                                    );
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                border:1px solid #1A44F744;
                                box-shadow:
                                    0 14px 35px rgba(0,0,0,.18),
                                    0 0 30px rgba(26,68,247,.15);
                            ">
                                <x-tour-icon
                                    name="leadership"
                                    size="30"
                                    style="color:#1A44F7"
                                />
                            </div>

                            {{-- Badge --}}
                            <div style="
                                position:absolute;
                                bottom:4px;
                                right:4px;
                                width:34px;
                                height:34px;
                                border-radius:50%;
                                background:var(--gold);
                                color:var(--bg-950);
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                box-shadow:
                                    0 8px 18px rgba(243,186,21,.35);
                                border:2px solid var(--bg-950);
                                font-size:.85rem;
                            ">
                                ★
                            </div>

                        </div>

                        {{-- Role --}}
                        <div style="
                            font-size:.68rem;
                            color:var(--gold);
                            font-weight:700;
                            text-transform:uppercase;
                            letter-spacing:.16em;
                            margin-bottom:10px;
                        ">
                            {{ __('Department Head') }}
                        </div>

                        {{-- Department --}}
                        <h3 class="am" style="
                            font-size:1rem;
                            font-weight:700;
                            color:var(--text-display);
                            margin-bottom:8px;
                            line-height:1.4;
                        ">
                            {{ $department->name_am ?? $department->name_en }}
                        </h3>

                        {{-- Head --}}
                        <div style="
                            font-size:.84rem;
                            color:var(--text-60);
                            line-height:1.7;
                        ">
                            {{ $department->headUserName }}
                        </div>

                    </div>
                </div>

            @endforeach

        </div>

    </div>
</section>