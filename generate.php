<?php

function generateRandomText(string $name = ''): string {

    $greetings = [
        "Halo",
        "Hai",
        "Hi",
        "Pagi",
        "Selamat pagi",
        "Siang",
        "Sore",
        "Malam",
    ];

    $openers = [
        "lagi apa sekarang",
        "gimana kabarnya hari ini",
        "semoga harinya berjalan lancar",
        "semoga sehat selalu",
        "hari ini sibuk tidak",
        "lagi santai atau kerja",
        "semoga semuanya baik",
        "hari ini cuacanya bagaimana",
        "semoga hari ini menyenangkan",
    ];

    $questions = [
        "sudah makan belum",
        "lagi di rumah atau di luar",
        "lagi fokus kerja ya",
        "hari ini banyak kegiatan",
        "lagi ngopi atau teh",
        "lagi denger musik juga",
        "lagi istirahat sebentar mungkin",
    ];

    $closings = [
        "semangat ya hari ini",
        "jangan lupa istirahat juga",
        "jaga kesehatan ya",
        "semoga semua urusan lancar",
        "semoga harinya menyenangkan",
        "semoga hari ini produktif",
        "mudah mudahan semua lancar",
        "tetap semangat jalani hari",
    ];

    $connectors = [
        "",
        "btw",
        "oh iya",
        "hmm",
        "ngomong ngomong",
        "eh iya",
    ];

    $emoji = [
        "",
        "",
        "",
        "🙂",
        "😊",
        "😄",
        "👍",
        "✨",
        "🙏"
    ];

    $greet = $greetings[array_rand($greetings)];

    if ($name) {
        $greet .= " $name";
    }

    $parts = [];

    $parts[] = $greet;
    $parts[] = $openers[array_rand($openers)];
    $parts[] = $questions[array_rand($questions)];
    $parts[] = $closings[array_rand($closings)];

    if (rand(0,3) === 1) {
        $parts[] = $connectors[array_rand($connectors)] . " " . randomFact();
    }

    shuffle($parts);

    $text = implode(", ", array_filter($parts));

    // typo natural kecil (kadang saja)
    if (rand(0,8) === 2) {
        $text = str_replace("tidak", "ga", $text);
    }

    if (rand(0,8) === 3) {
        $text = str_replace("sudah", "udah", $text);
    }

    return ucfirst($text) . " " . $emoji[array_rand($emoji)];
}


function randomFact(): string {

    $facts = [
        "katanya minum air putih pagi hari bagus buat metabolisme tubuh",
        "katanya denger musik favorit bisa bikin kerja lebih fokus",
        "ternyata jalan kaki sebentar bisa bantu refresh pikiran",
        "istirahat 5 menit kadang bikin kerja lebih produktif",
        "tersenyum sebentar juga bisa bikin mood lebih baik",
        "tanaman di ruangan kerja katanya bisa bikin lebih rileks",
        "tidur siang sebentar kadang bikin energi balik lagi",
        "minum air cukup itu penting juga buat fokus kerja",
        "katanya udara pagi bagus buat pikiran lebih fresh",
        "kadang stretching sebentar juga bagus buat badan",
    ];

    return $facts[array_rand($facts)];
}