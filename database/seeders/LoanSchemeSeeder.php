<?php

namespace Database\Seeders;

use App\Models\LoanScheme;
use Illuminate\Database\Seeder;

class LoanSchemeSeeder extends Seeder
{
    public function run(): void
    {
        $schemes = [
            ['bank_name' => 'Assam Gramin Bank',            'acronym_name' => 'AGB',   'scheme_name' => 'Mahila Udyami Scheme'],
            ['bank_name' => 'Assam Co-Operative Apex Bank', 'acronym_name' => 'ACAB',  'scheme_name' => 'Lakhpati Didi Scheme'],
            ['bank_name' => 'Bank of Baroda',               'acronym_name' => 'BOB',   'scheme_name' => 'Naari Shakti Scheme'],
            ['bank_name' => 'Bank of India',                'acronym_name' => 'BOI',   'scheme_name' => 'Star Sakhi Scheme'],
            ['bank_name' => 'Bank of Maharastra',           'acronym_name' => 'BOM',   'scheme_name' => 'Maha Lakhpati Didi Scheme'],
            ['bank_name' => 'Central Bank of India',        'acronym_name' => 'CBI',   'scheme_name' => 'Cent Naari Shakti Scheme'],
            ['bank_name' => 'Canara Bank',                  'acronym_name' => 'CB',    'scheme_name' => 'Canara Stree Shakti'],
            ['bank_name' => 'IDBI Bank',                    'acronym_name' => 'IDBI',  'scheme_name' => 'Micro Entrepreneur Scheme'],
            ['bank_name' => 'Indian Bank',                  'acronym_name' => 'IB',    'scheme_name' => 'IND MSME Sakhi'],
            ['bank_name' => 'Indian Bank',                  'acronym_name' => 'IB',    'scheme_name' => 'IND Lakhpati Didi'],
            ['bank_name' => 'Indian Overseas Bank',         'acronym_name' => 'IOB',   'scheme_name' => 'Mahila Saksham Scheme'],
            ['bank_name' => 'Punjab National Bank',         'acronym_name' => 'PNB',   'scheme_name' => 'SHG Shreshtha Scheme'],
            ['bank_name' => 'Punjab and Sind Bank',         'acronym_name' => 'PSB',   'scheme_name' => 'PSB Lakhpati Didi Scheme'],
            ['bank_name' => 'State Bank of India',          'acronym_name' => 'SBI',   'scheme_name' => 'Svayam Siddha Scheme'],
            ['bank_name' => 'State Bank of India',          'acronym_name' => 'SBI',   'scheme_name' => 'Nari Shakti Scheme'],
            ['bank_name' => 'UCO Bank',                     'acronym_name' => 'UCO',   'scheme_name' => 'Nari Samriddhi Yojana'],
            ['bank_name' => 'Union Bank of India',          'acronym_name' => 'UBI',   'scheme_name' => 'Union Lakhpathi Didi'],
        ];

        foreach ($schemes as $scheme) {
            LoanScheme::updateOrCreate(
                ['bank_name' => $scheme['bank_name'], 'scheme_name' => $scheme['scheme_name']],
                ['acronym_name' => $scheme['acronym_name']]
            );
        }
    }
}
