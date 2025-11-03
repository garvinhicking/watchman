<?php

use GuzzleHttp\Client;

require_once(__DIR__ . '/../vendor/autoload.php');

final class Watchman
{
    private const string CACHE_DIR = __DIR__ . '/../cache/';
    private const string FINAL_DIR = __DIR__ . '/../public/';

    private string $token = '';
    private string $genres = '10759|16|35|80|99|18|10751|10762|9648|10765|37';
    private string $watchProviders = '2|8|9|10|15|27|29|30|31|34|37|38|43|78|79|80|83|118|119|123|130|148|156|157|194|198|201|203|206|209|210|215|219|234|243|263|298|304|317|318|322|328|332|337|345|348|350|363|381|384|386|387|390|418|421|425|459|484|531|613|630|1770|1796|1825|1902';

    private array $lookup = [
        'genres' => [],
        'providers' => [],
        'series' => [],
        'filtered' => [],
        'networks' => [],
        'changedNetworks' => [],
        'useNetworks' => [],
    ];

    private Client $client;

    public function __construct(
        private readonly string $dateStart,
        private readonly string $dateEnd,
    ) {
        $tokenfile = realpath(__DIR__ . '/../private/token.env');
        if (!file_exists($tokenfile)) {
            throw new LogicException('Missing file:' . $tokenfile);
        }
        $this->token = trim(file_get_contents($tokenfile));
        echo "Discovering from " . $dateStart . " to " . $dateEnd . "\n";
        if (!is_dir(self::CACHE_DIR)) {
            mkdir(self::CACHE_DIR, 0777, true);
        }
        if (!is_dir(self::FINAL_DIR)) {
            mkdir(self::FINAL_DIR, 0777, true);
        }

        $networkCache = 'tv_network_ids_' . date('m_d_Y') . '.json';
        if (!file_exists($networkCache)) {
            echo "Fetching network IDs\n";
            $this->lookup['networks'] = $this->getUnzipped($networkCache, 'https://files.tmdb.org/p/exports/' . $networkCache . '.gz');
        } else {
            echo "Getting networks from cache\n";
            $this->lookup['networks'] = json_decode(file_get_contents($networkCache), true);
        }
        $this->client = new Client();
        $this->lookupGenres();
        $this->lookupWatchProviders();
        $this->checkProviders();
    }

    private function getUnzipped(string $networkCache, string $uri): array
    {
        $gzFile = self::CACHE_DIR . '/networks.gz';
        $outFile = self::CACHE_DIR . '/' . $networkCache;
        $tmpOutFile = $outFile . '.tmp';
        $fp = fopen($gzFile, 'wb');
        $bytes = fwrite($fp, file_get_contents($uri));
        fclose($fp);

        echo "Cached $uri into $networkCache with " . $bytes . " Bytes.\n";

        $file = gzopen($gzFile, 'rb');
        $out_file = fopen($tmpOutFile, 'wb');
        while (!gzeof($file)) {
            fwrite($out_file, gzread($file, 4096));
        }
        fclose($out_file);
        gzclose($file);

        // turn into real JSON
        $contents = str_replace('}', '},', file_get_contents($tmpOutFile)) . '{"last": "true"}';
        $contents = '[' . $contents . ']';
        $fp = fopen($outFile, 'wb');
        fwrite($fp, $contents);
        fclose($fp);
        echo "Extracted GZIP into " . strlen($contents) . " bytes.\n";

        unlink($tmpOutFile);
        return json_decode($contents, true);
    }

    public function checkProviders(bool $triggerUpdate = true): void
    {
        $allowed = [
            'arte.tv',
            5522,
            'ZDF',
            31,
            'Warner TV',
            5016,
            'Universal',
            7305,
            'USA Network',
            30,
            'The WB',
            21,
            'The Comedy Channel',
            102,
            'The Comedy Network',
            51,
            'The CW',
            71,
            'TMC',
            1133,
            'Syfy',
            6914,
            'Starz',
            318,
            'Showtime',
            67,
            'Showtime 2',
            7875,
            'SAT.1',
            163,
            'RTL+',
            7199,
            'RTL2',
            1716,
            'ProSieben',
            334,
            'Prime Video',
            1024,
            'Prime',
            1567,
            'Paramount+',
            6445,
            'Paramount+ with Showtime',
            6631,
            'Paramount Network',
            6101,
            'PBS',
            14,
            'Netflix',
            213,
            'NBCUniversal',
            8627,
            'NBC',
            2462,
            'MGM+',
            8522,
            'Lionsgate+',
            6293,
            'Joyn',
            7216,
            'Hulu',
            1772,
            'HBO',
            8102,
            'HBO Go',
            7368,
            'HBO Max',
            8304,
            'Fox',
            961,
            'FOX',
            8003,
            'DisneyNOW',
            8429,
            'Disney+',
            2739,
            'Das Erste',
            308,
            'Cartoon Network',
            8505,
            'CBS',
            16,
            'CBC',
            2548,
            'BBC',
            4796,
            'BBC One',
            4,
            'BBC Two',
            332,
            'BBC iPlayer',
            1155,
            'Arte',
            7751,
            'Apple TV',
            2552,
            'Amazon Freevee',
            7385,
            'Adult Swim',
            8233,
            'AdultSwim.com',
            7377,
            'ARTE',
            1628,
            'ARD',
            8129,
            'AMC+',
            4661,
            'AMC.com',
            6400,
            'AMC',
            8657,
            'ABS',
            4818,
            'ABC',
            2,
            'ABC Comedy',
            7686,
            'ABC TV',
            1410,
            'A&E',
            8650,
        ];
        $ignored = [
            'Č',
            'ス',
            '百',
            '동',
            '合',
            '국',
            '큐',
            '캐',
            '중',
            '재',
            '강',
            '時',
            '视',
            'ミ',
            'ف',
            'Đ',
            'č',
            'İ',
            'Ж',
            'Л',
            'ш',
            'Nogoum',
            'THT',
            'tvN ',
            'kyNET',
            'Zee ',
            'iQIYI',
            'YouTube',
            'Yle ',
            'Yahoo! ',
            'Televizija',
            'Televisión',
            'TRT ',
            'TNT ',
            'SBS ',
            'Russia',
            'Rai ',
            'RaiSat',
            'RTÉ',
        ];

        $ignoredIds = [
            7685,
            6634,
        ];

        $idList = [];
        $nameList = [];
        $filtered = [];
        foreach ($this->lookup['networks'] as $network) {
            if (isset($network['last'])) {
                continue;
            }
            if (in_array($network['id'], $ignoredIds)) {
                #echo 'Filter ' . $network['name'] . "\n";
                $filtered[] = $network['name'];
                continue;
            }

            if (in_array($network['name'], $allowed)) {
                $idList[$network['id']] = $network['name'];
            }
            if (in_array($network['id'], $allowed)) {
                $idList[$network['id']] = $network['name'];
            }

            if (!preg_match('@[a-z]@i', $network['name'])) {
                #echo 'Filter ' . $network['name'] . "\n";
                $filtered[] = $network['name'];
                continue;
            }
            if (preg_match('@' . implode('|', $ignored) . '@i', $network['name'])) {
                #echo 'Filter ' . $network['name'] . "\n";
                $filtered[] = $network['name'];
                continue;
            }

            if (isset($nameList[$network['name']])) {
                #echo "DUPE: " . $network['name'] . '#' . $network['id'] . ' already as ' . $nameList[$network['name']] . "\n";
            }
            $nameList[$network['name']] = $network['id'];
        }
        ksort($nameList);
        asort($idList);

        echo count($idList) . " Providers to evaluate.\n";

        $lastGoodNetworksFile = self::CACHE_DIR . '/last_good_networks.json';
        if (file_exists($lastGoodNetworksFile)) {
            $lastGood = json_decode(file_get_contents($lastGoodNetworksFile), true);
            $differencesDetail = array_merge(array_diff_assoc($nameList, $lastGood), array_diff_assoc($lastGood, $nameList));
            $this->lookup['changedNetworks'] = $differencesDetail;
        }

        $fp = fopen(self::CACHE_DIR . '/filtered_networks.json', 'wb');
        fwrite($fp, json_encode($filtered, JSON_PRETTY_PRINT));
        fclose($fp);

        if ($triggerUpdate) {
            $fp = fopen($lastGoodNetworksFile, 'wb');
            fwrite($fp, json_encode($nameList, JSON_PRETTY_PRINT));
            fclose($fp);
        }

        $this->lookup['useNetworks'] = array_keys($idList);
    }

    private function lookupGenres(): void
    {
        $json = $this->getData('genres', 'https://api.themoviedb.org/3/genre/tv/list?language=en');
        foreach ($json['genres'] as $genre) {
            $this->lookup['genres'][$genre['id']] = $genre['name'];
        }
    }

    private function lookupWatchProviders(): void
    {
        $json = $this->getData('providers', 'https://api.themoviedb.org/3/watch/providers/tv?language=en-US');
        foreach ($json['results'] as $genre) {
            $this->lookup['providers'][$genre['provider_id']] = $genre['provider_name'];
        }
    }

    private function lookupDetails(int $seriesId): array
    {
        $json = $this->getData('details', 'https://api.themoviedb.org/3/tv/' . $seriesId . '?language=en-US&append_to_response=aggregate_credits');
        $this->lookup['details'][$seriesId] = $json;
        return $json;
    }

    private function getData(string $identifier, string $uri, int $page = 0, bool $bypassCache = false): array
    {
        $cacheFile = self::CACHE_DIR . DIRECTORY_SEPARATOR . $identifier . '_' . sha1($uri) . '-' . $page . '.json';
        if (!$bypassCache && file_exists($cacheFile) && filemtime($cacheFile) > (time()-86400)) {
            $this->log('(Cached)');
            $body = file_get_contents($cacheFile);
        } else {
            $this->log('(Live)');
            $response = $this->client->request(
                'GET',
                $uri,
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->token,
                        'accept'        => 'application/json',
                    ],
                ],
            );
            sleep(1);
            $body = $response->getBody()->getContents();
            $fp = fopen($cacheFile, 'w');
            fwrite($fp, $body);
            fclose($fp);
        }

        return json_decode($body, true);
    }

    public function recursiveDiscover(int $page = 1, bool $bypassCache = false): void {

        $uri = 'https://api.themoviedb.org/3/discover/tv?'
            . 'air_date.gte=' . $this->dateStart
            . '&air_date.lte=' . $this->dateEnd
            . '&include_adult=true'
            . '&include_null_first_air_dates=false'
            . '&language=en-US'
            . '&page=' . $page
            . '&sort_by=popularity.desc'
            . '&with_genres=' . urlencode($this->genres)
            . '&without_genres=' . urlencode('10763|10764|10766|10767|10768')
            . '&with_original_language=de%7Cen%7Cja%7Cko'
            . '&with_networks=' . urlencode(implode('|', $this->lookup['useNetworks']));
        //. '&with_watch_providers=' . urlencode($this->watchProviders);

        $json = $this->getData('discover', $uri, $page, $bypassCache);
        $this->log('Page ' . $json['page'] . '/' . $json['total_pages'] . ': ' . count($json['results']) . ' Results of ' . $json['total_results']);

        foreach ($json['results'] as $result) {
            $this->lookup['series'][$result['id']] = [
                'name' => $result['name'],
                'description' => $result['overview'],
                'popularity' => $result['popularity'],
                'airdate' => $result['first_air_date'],
                'votes' => $result['vote_average'] . '@' . $result['vote_count'],
                'logo' => 'https://media.themoviedb.org/t/p/w220_and_h330_face' . $result['poster_path'],
                'genres' => $this->performLookup('genres', $result['genre_ids']),
            ];
        }

        if ($json['page'] < $json['total_pages']) {
            $this->recursiveDiscover($page + 1);
        }
    }

    public function iterateSeries(): void
    {
        $count = count($this->lookup['series']);
        $index = 0;
        foreach ($this->lookup['series'] as $id => $series) {
            $index++;
            $this->log($index . '/' . $count ." - enriching #$id");
            $this->lookup['series'][$id]['details'] = $this->lookupDetails($id);
        }
    }

    public function compile(): void
    {
        file_put_contents(self::CACHE_DIR . DIRECTORY_SEPARATOR . 'compiled.json', json_encode($this->lookup['series'], JSON_PRETTY_PRINT));

        $indent = '    ';
        $aggregation = [];
        $this->log("Compiling " . count($this->lookup['series']) . " series ...");
        $compiled = 0;
        foreach ($this->lookup['series'] as $id => $series) {
            if (($series['details']['type'] ?? 'unknown') === 'Reality') {
                $this->lookup['filtered'][] = 'Filtered reality: ' . $series['name'];
                continue;
            }
            if (implode(',', $series['details']['languages'] ?? '') === 'id') {
                $this->lookup['filtered'][] = 'Filtered indonesian: ' . $series['name'];
                continue;
            }
            $output = $indent . '<h2 data-id="' . $id . '"><a href="' . htmlspecialchars($series['details']['homepage'] ?? '#') . '" target="_blank">' . htmlspecialchars($series['name']) . '</a></h2>' . "\n";
            $season = $series['details']['next_episode_to_air']['season_number'] ?? 0;
            $episode = $series['details']['next_episode_to_air']['episode_number'] ?? 0;

            if ($season === 0) {
                $season = $series['details']['last_episode_to_air']['season_number'] ?? 0;
            }
            if ($episode === 0) {
                $episode = $series['details']['last_episode_to_air']['episode_number'] ?? 0;
            }

            if ($season === 1 && $episode === 1) {
                $status = 'SERIES PREMIERE';
            } elseif ($episode === 1) {
                $status = 'SEASON PREMIERE';
            } elseif ($episode === $series['details']['number_of_episodes'] ?? -1) {
                $status = 'FULLDUMP';
            } else {
                $this->lookup['filtered'][] = 'Filtered #' . $id . ' S' . $season . 'E' . $episode . ': ' . $series['name'];
                continue;
            }
            $output .= $indent . '<p><strong style="color: red">Status: ' . $status . ' (' . ($series['details']['type'] ?? 'unknown') . ', ' . ($series['details']['status'] ?? 'unknown') . ')</strong></p>' . "\n";
            $output .= $indent . '<p><img src="' . htmlspecialchars($series['logo']) . '" alt="(Cover)" loading="lazy"></p>' . "\n";
            $output .= $indent . '<p>(' . htmlspecialchars($series['genres']) . ') ' . htmlspecialchars($series['description']) . '</p>' . "\n";
            $output .= $indent . '<p><strong>' . date('d.m.Y', strtotime($series['details']['next_episode_to_air']['airdate'] ?? $series['details']['last_episode_to_air']['airdate'] ?? $series['airdate'] ?? date('Y-m-d'))) . '</strong></p>' . "\n";
            $output .= $indent . '<p><em>' . htmlspecialchars($series['popularity'] . ' - ' . $series['votes']) . '</em></p>' . "\n";

            $creators = [];
            foreach ($series['details']['created_by'] ?? [] as $creator) {
                $creators[] = $creator['name'];
            }
            if ($creators !== []) {
                $output .= $indent . '<p>Created by: ';
                $output .= htmlspecialchars(implode(', ', $creators)) . '</p>' . "\n";
            }

            $cast = [];
            foreach ($series['details']['aggregate_credits']['cast'] ?? [] as $person) {
                $cast[] = $person['name'];
            }
            if ($cast !== []) {
                $output .= $indent . '<p>Cast: ';
                $output .= htmlspecialchars(implode(', ', $cast)) . '</p>' . "\n";
            }

            $network = $series['details']['networks'][0]['name'] ?? '(Unknown)';
            if (!isset($aggregation[$network])) {
                $aggregation[$network] = [];
            }
            $aggregation[$network][] = $output;
            $compiled++;
        }

        $this->log("Compiled " . $compiled . " series.");

        $html = '<html><head><title>Watchman</title></head><body>';
        $html .= '<p>All data and linked images courtesy and provided by <a href="https://www.themoviedb.org/about/logos-attribution">The Movie DB (TMDB)</a></p>';
        $html .= '<form>' . "\n\n";
        foreach ($aggregation as $network => $output) {
            $html .= '<fieldset style="border: 2px solid red">' . "\n";
            $html .= '<legend>' . $network . '</legend>' . "\n";
            $html .= '<div>' . "\n";
            $html .= implode("\n\n<hr>\n\n", $output) . "\n";
            $html .= '</div>' . "\n";
            $html .= '</fieldset>' . "\n\n";
        }
        $html .= "\n\n";

        $html .= "Network changes:<ol>\n";
        foreach ($this->lookup['changedNetworks'] as $networkName) {
            $html .= '<li>' . $networkName . '</li>' . "\n";
        }
        $html .= '</ol>' . "\n";
        $html .= "Filtered:\n<ol>\n";
        foreach($this->lookup['filtered'] as $filtered) {
            $html .= '<li>' . $filtered . '</li>' . "\n";
        }
        $html .= '</ol>' . "\n";
        $html .= '</form></body></html>';

        file_put_contents(self::FINAL_DIR . DIRECTORY_SEPARATOR . '/index.html', $html);
        file_put_contents(self::FINAL_DIR . DIRECTORY_SEPARATOR . 'archive-' . date('Y-m-d') . '.html', $html);
    }

    private function performLookup(string $type, $dataArray): string
    {
        $out = [];
        foreach ($dataArray as $item) {
            if (isset($this->lookup[$type][$item])) {
                $out[] = $this->lookup[$type][$item];
            } else {
                $out[] = '#' . $item;
            }
        }
        return implode(', ', $out);
    }

    public function log(string $message): void
    {
        echo $message . "\n";
        $fp = fopen(self::CACHE_DIR . '/messages.log', 'a');
        fwrite($fp, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n");
        fclose($fp);
    }
}

#$watchman = new Watchman('2025-10-01', '2025-11-09');
$watchman = new Watchman(date('Y-m-d'), date('Y-m-d', time()+86400*7));
$watchman->recursiveDiscover(1);
$watchman->iterateSeries();
$watchman->compile();
$watchman->log('Done');
