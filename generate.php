<?php

function generateRandomText(string $name = ''): string {
    $greeting = $name ? "Halo $name" : 'Halo';

    $templates = [
        // Sapaan & kabar
        "$greeting! Sudah makan belum hari ini? Jangan sampai lupa makan ya 😊",
        "$greeting! Bagaimana kabarnya hari ini? Semoga sehat selalu!",
        "$greeting! Lagi sibuk apa sekarang? Semoga harimu menyenangkan 🌟",
        "$greeting! Sudah minum air putih belum? Jaga kesehatan ya!",
        "$greeting! Kabar baik nih, semoga harimu penuh semangat! 💪",

        // Motivasi
        "Semangat pagi! Setiap hari adalah kesempatan baru untuk menjadi lebih baik. 🌅",
        "Ingat, setiap langkah kecil tetap membawa kamu lebih dekat ke tujuan. Terus maju! 🚀",
        "Hari ini adalah hari yang tepat untuk melakukan hal-hal luar biasa! 🌟",
        "Jangan menyerah! Yang terbaik selalu butuh waktu dan proses. ✨",
        "Senyum dulu, hari ini pasti bisa dilewati dengan baik! 😄",

        // Pertanyaan ringan
        "Hei! Kamu sedang dengarkan lagu apa sekarang? Atau lagi sepi-sepian? 🎵",
        "Btw, sudah makan siang belum? Rekomendasiin dong makanan enak hari ini! 🍱",
        "Weekend kemarin seru nggak? Ada cerita menarik? 😄",
        "Lagi mood minum kopi atau teh hari ini? ☕🍵",
        "Sudah olahraga hari ini? Kalau belum, yuk gerak dikit! 🏃",

        // Fakta & info ringan
        randomFact(),
    ];

    return $templates[array_rand($templates)];
}

function randomFact(): string {
    $facts = [
        "Tahukah kamu? Tersenyum selama 20 detik bisa meningkatkan mood secara signifikan! 😊 Coba sekarang!",
        "Fakta menarik: Minum segelas air putih di pagi hari membantu metabolisme tubuh bekerja lebih baik. 💧",
        "Did you know? Mendengarkan musik favorit bisa meningkatkan produktivitas kerja hingga 15%. 🎵",
        "Info: Tidur siang singkat 20 menit lebih efektif memulihkan energi daripada tidur lebih lama. 😴",
        "Tahukah kamu? Tertawa selama 10 menit setara dengan olahraga ringan bagi jantungmu. 😂❤️",
        "Fakta: Tanaman di ruangan bisa mengurangi stres dan meningkatkan konsentrasi. Punya tanaman hias? 🌿",
        "Tips hari ini: Istirahat 5 menit setiap jam kerja terbukti meningkatkan fokus dan produktivitas! ⏰",
        "Tahukah kamu? Mengucapkan terima kasih secara rutin terbukti membuat orang lebih bahagia. Makasih ya sudah ada! 🙏",
    ];

    return $facts[array_rand($facts)];
}
