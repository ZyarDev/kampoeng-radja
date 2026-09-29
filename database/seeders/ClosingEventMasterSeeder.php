<?php

namespace Database\Seeders;

use App\Models\JenisEvent;
use App\Models\Lokasi;
use App\Models\Pic;
use Illuminate\Database\Seeder;

class ClosingEventMasterSeeder extends Seeder
{
    public function run(): void
    {
        $pics = [
            'MARKOM',
            'IPADA',
            'PUTRI',
            'ELIZA',
            'AJENG',
            'AMEL',
            'DINDA',
        ];

        foreach ($pics as $namaPic) {
            Pic::firstOrCreate([
                'nama_pic' => $namaPic,
            ]);
        }

        $jenisEvents = [
            'REKREASI',
            'FAMILY GATHERING - PLATINUM',
            'FAMILY GATHERING - GOLD',
            'FAMILY GATHERING - SILVER',
            'WISDU AGROWISATA',
            'WISDU BATIK JUMPUTAN',
            'WISDU MEWARNAI',
            'LIBURAN SEKOLAH',
            'MAKRAB',
            'CAMPING',
            'OLAHRAGA REKREASI',
            'OUTING CLASS',
            'SEKOLAH MINGGU',
            'LDK',
            'IBADAH KRISTIANI',
            'KOMUNITAS',
            'ARISAN',
            'RAMADHAN CAMP',
            'ENGLISH CAMP',
            'ULANG TAHUN',
            'PERPISAHAN SEKOLAH',
            'EVENT MARKOM',
            'FUN SWIMMING',
            'SAFARI SANTRI',
            'PTA',
            'MANASIK HAJI',
            'STAYCATION',
            'SAHARA',
        ];

        foreach ($jenisEvents as $jenisEvent) {
            JenisEvent::firstOrCreate([
                'jenis_event' => $jenisEvent,
            ]);
        }

        $lokasi = [
            'ISTANA BALON',
            'AREA CAMPING',
            'LAPANGAN OUTBOUND',
            'TENDA PANGGUNG',
            'TRAP 4',
            'LAPANGAN JWP',
            'MMLT 1',
            'GAMES POS 1',
            'MM LT 2',
            'GAZEBO OA',
            'GAZEBO RESTO BESAR',
        ];

        foreach ($lokasi as $namaLokasi) {
            Lokasi::firstOrCreate([
                'nama_lokasi' => $namaLokasi,
            ]);
        }
    }
}