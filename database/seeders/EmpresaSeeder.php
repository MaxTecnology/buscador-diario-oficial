<?php

namespace Database\Seeders;

use App\Models\Empresa;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmpresaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $empresas = [
            [
                'nome' => 'Petróleo Brasileiro S.A.',
                'cnpj' => '33000167000101',
                'inscricao_estadual' => '85.219.530',
                'prioridade' => 'alta',
                'score_minimo' => 0.95,
                'termos_personalizados' => ['Petrobras', 'BR Distribuidora', 'Transpetro'],
                'ativo' => true,
            ],
            [
                'nome' => 'Vale S.A.',
                'cnpj' => '33592510000154',
                'inscricao_estadual' => '062.342.990.112',
                'prioridade' => 'alta',
                'score_minimo' => 0.90,
                'termos_personalizados' => ['Vale do Rio Doce', 'Minerações Brasileiras'],
                'ativo' => true,
            ],
            [
                'nome' => 'Banco do Brasil S.A.',
                'cnpj' => '00000000000191',
                'inscricao_estadual' => '070.331.001.112',
                'prioridade' => 'alta',
                'score_minimo' => 0.85,
                'termos_personalizados' => ['BB', 'Banco do Brasil'],
                'ativo' => true,
            ],
            [
                'nome' => 'Ambev S.A.',
                'cnpj' => '07526557000100',
                'inscricao_estadual' => '104.892.012.119',
                'prioridade' => 'media',
                'score_minimo' => 0.80,
                'termos_personalizados' => ['Brahma', 'Skol', 'Antarctica'],
                'ativo' => true,
            ],
            [
                'nome' => 'JBS S.A.',
                'cnpj' => '02916265000160',
                'inscricao_estadual' => '283.781.804.113',
                'prioridade' => 'media',
                'score_minimo' => 0.75,
                'termos_personalizados' => ['Friboi', 'Swift', 'Seara'],
                'ativo' => true,
            ],
            [
                'nome' => 'Construtora Norberto Odebrecht S.A.',
                'cnpj' => '15102288000182',
                'inscricao_estadual' => '292.509.186.117',
                'prioridade' => 'alta',
                'score_minimo' => 0.95,
                'termos_personalizados' => ['Odebrecht', 'Construtora Norberto'],
                'ativo' => true,
            ],
            [
                'nome' => 'Embraer S.A.',
                'cnpj' => '07689002000189',
                'inscricao_estadual' => '645.191.560.118',
                'prioridade' => 'media',
                'score_minimo' => 0.80,
                'termos_personalizados' => ['Empresa Brasileira de Aeronáutica'],
                'ativo' => true,
            ],
            [
                'nome' => 'Gerdau S.A.',
                'cnpj' => '33611500000191',
                'inscricao_estadual' => '270.053.798.112',
                'prioridade' => 'media',
                'score_minimo' => 0.75,
                'termos_personalizados' => ['Aços Gerdau', 'Siderúrgica Gerdau'],
                'ativo' => true,
            ],
            [
                'nome' => 'Marfrig Global Foods S.A.',
                'cnpj' => '03853896000136',
                'inscricao_estadual' => '147.166.795.115',
                'prioridade' => 'baixa',
                'score_minimo' => 0.70,
                'termos_personalizados' => ['Marfrig', 'Bassi', 'Pemmex'],
                'ativo' => true,
            ],
            [
                'nome' => 'Localiza Rent a Car S.A.',
                'cnpj' => '16670085000155',
                'inscricao_estadual' => '062.054.658.0001',
                'prioridade' => 'baixa',
                'score_minimo' => 0.70,
                'termos_personalizados' => ['Localiza', 'Unidas'],
                'ativo' => false,
            ],
        ];

        foreach ($empresas as $empresa) {
            Empresa::create($empresa);
        }
    }
}