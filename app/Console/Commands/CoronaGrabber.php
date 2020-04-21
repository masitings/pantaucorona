<?php

namespace App\Console\Commands;

use App\Kasus;
use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Events\CaseUpdated;
use Illuminate\Console\Command;
use KubAT\PhpSimple\HtmlDomParser;
use Illuminate\Support\Facades\App;

class CoronaGrabber extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'corona:grab';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Grab Info Corona';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->info('Picking info Corona');
        
        try{
            $data = $this->getDatav2();
        }catch(\Exception $e)
        {
            throw new \Exception('failed get data');
        }
        
        $old_kasus = Kasus::latest()->first();

        $kasus = Kasus::create($data);
        $this->info('Info virus corona');
        $this->line('Total Kasus: '. $kasus->total_case);
        $this->line('Kasus Baru: '. $kasus->new_case);
        $this->line('Total Kematian: '. $kasus->total_death);
        $this->line('Kematian Baru: '. $kasus->new_death);
        $this->line('Total Sembuh: '. $kasus->total_recovered);
        $this->line('Kasus Aktif: '. $kasus->active_case);
        $this->line('Kasus Kritis: '. $kasus->critical_case);

        if (!App::environment('local')) {
            event(new CaseUpdated($old_kasus, $kasus));
        }
    }

    protected function getDatav2()
    {
        $client = new Client([
            'timeout' => 10.0
        ]);

        $response = $client->request('GET', 'https://kawalcovid19.harippe.id/api/summary');
        $result = json_decode($response->getBody()->getContents());

        $yesterday_case = Kasus::whereDate('created_at', Carbon::yesterday())->latest()->first();

        $data = [
            'total_case' => $result->confirmed->value,
            'new_case' => $result->confirmed->value - $yesterday_case->total_case,
            'total_death' => $result->deaths->value,
            'new_death' => $result->deaths->value - $yesterday_case->total_death,
            'total_recovered' => $result->recovered->value,
            'new_recovered' => $result->recovered->value - $yesterday_case->total_recovered,
            'active_case' => $result->activeCare->value,
            'critical_case' => 0
        ];

        return $data;
    }

    /**
     * get data
     *
     * @return array
     */
    protected function getData() : array
    {
        $html = $this->getHtml();

        $array = $this->getArray($html);

        return $array;
    }

    /**
     * parse data as array
     *
     * @param string $html
     * @return array
     */
    protected function getArray(string $html) : array
    {
        define('MAX_FILE_SIZE', 1200000);
        try{
            $dom = HtmlDomParser::str_get_html($html);
        }catch(\Exception $e)
        {
            throw new \Exception("Error Processing Request", 1);
        }

        $data = [];
        foreach($dom->find('table.table-bordered tbody tr') as $row)
        {
            if( strpos(strtolower($row->find('td', 0)->plaintext), 'indonesia'))
            {
                // dd($row->find('td', 1)->plaintext);
                $data['total_case'] = (int) $row->find('td', 1)->plaintext;
                $data['new_case'] = (int) $row->find('td', 2)->plaintext;
                $data['total_death'] = (int) $row->find('td', 3)->plaintext;
                $data['new_death'] = (int) $row->find('td', 4)->plaintext;
                $data['total_recovered'] = (int) $row->find('td', 5)->plaintext;
                $data['active_case'] = (int) $row->find('td', 6)->plaintext;
                $data['critical_case'] = (int) $row->find('td', 7)->plaintext;
            }
        }

        return $data;
    }

    protected function getHtml() : string
    {
        $url = config('corona.source_url');
        
        $client = new Client([
            // You can set any number of default request options.
            'timeout'  => 30.0,
        ]);

        $response = $client->request('GET', $url);

        return $response->getBody()->getContents();
    }
}
