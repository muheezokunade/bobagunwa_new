{{--
    The association's five commitments ("We are committed to…", About Us document,
    3 Oct 2026), summarised as value tiles at Gunwa Abiodun Orehin's request (22 Nov 2026 demo).
    DRAFT: the Yoruba words and one-line summaries await the association's confirmation (docs/39).
--}}
@php
    $values = [
        ['Progress', 'Ìtẹ̀síwájú', 'Promoting the social and economic development of our members, Akile Ijebu and Nigeria.'],
        ['Character', 'Ìwà Ọmọlúàbí', 'Upholding Omoluabi, respect and integrity as we sustain the cherished heritage of Akile Ijebu.'],
        ['Protection', 'Ààbò', 'Protecting the interest of every member in all ramifications.'],
        ['Unity', 'Ìṣọ̀kan', 'Fostering understanding with similar groups for the unity and progress of Akile Ijebu and the whole country.'],
        ['Service', 'Iṣẹ́ Ìsìn', 'Contributing to the development of Akile Ijebu through activities that serve our people.'],
    ];
@endphp
<div class="values-row values-five">
    @foreach ($values as $i => [$english, $yoruba, $line])
        <article><p class="meta"><span>{{ sprintf('%02d', $i + 1) }}</span><b>{{ $english }}</b></p><h3 lang="yo">{{ $yoruba }}</h3><p>{{ $line }}</p></article>
    @endforeach
</div>
