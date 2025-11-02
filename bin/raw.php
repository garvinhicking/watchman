<?php

use GuzzleHttp\Client;

require_once(__DIR__ . '/../vendor/autoload.php');

final class Watchman
{
    private const string TOKEN = 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiIyY2NjNjdlZDE3ZTNlMzFiY2JjYWJhZGY0NWZiZjZiYiIsIm5iZiI6MTc0NzcyOTU3MC42MDgsInN1YiI6IjY4MmMzY2EyNjNmYzNjMjI4YTJmNzg1MCIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.ApsoowYy0NLPM6EWtjtTM8abzG2uBVfaDEUbpsR7SHQ';
    private const string CACHE_DIR = __DIR__ . '/../cache/';

    private string $genres = '10759|16|35|80|99|18|10751|10762|9648|10765|37';
    private string $watchProviders = '2|8|9|10|15|27|29|30|31|34|37|38|43|78|79|80|83|118|119|123|130|148|156|157|194|198|201|203|206|209|210|215|219|234|243|263|298|304|317|318|322|328|332|337|345|348|350|363|381|384|386|387|390|418|421|425|459|484|531|613|630|1770|1796|1825|1902';

    private array $lookup = [
        'genres' => [],
        'providers' => [],
        'series' => [],
    ];

    private Client $client;

    public function __construct(
        private readonly string $dateStart,
        private readonly string $dateEnd,
    ) {
        if (!is_dir(self::CACHE_DIR)) {
            mkdir(self::CACHE_DIR, 0777, true);
        }
        $this->client = new Client();
        $this->lookupGenres();
        $this->lookupWatchProviders();

        print_r($this->lookup);
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
        if (!$bypassCache && file_exists($cacheFile)) {
            echo "(Cached)\n";
            $body = file_get_contents($cacheFile);
        } else {
            echo "(Live)\n";
            $response = $this->client->request(
                'GET',
                $uri,
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . self::TOKEN,
                        'accept'        => 'application/json',
                    ],
                ],
            );

            $body = $response->getBody()->getContents();
            $fp = fopen($cacheFile, 'w');
            fwrite($fp, $body);
            fclose($fp);
        }

        return json_decode($body, true);
    }

    public function recursiveDiscover(int $page = 1, bool $bypassCache = false): void {

        $uri = 'https://api.themoviedb.org/3/discover/tv?'
            . 'first_air_date.gte=' . $this->dateStart
            . '&first_air_date.lte=' . $this->dateEnd
            . '&include_adult=true'
            . '&include_null_first_air_dates=false'
            . '&language=en-US'
            . '&page=' . $page
            . '&sort_by=popularity.desc'
            . '&with_genres=' . urlencode($this->genres)
            . '&with_original_language=de%7Cen'
            . '&with_watch_providers=' . urlencode($this->watchProviders);

        $json = $this->getData('discover', $uri, $page, $bypassCache);
        echo 'Page ' . $json['page'] . '/' . $json['total_pages'] . ': ' . count($json['results']) . ' Results of ' . $json['total_results'] . "\n";

        print_r($json['results']);

        foreach ($json['results'] as $result) {
            $this->lookup['series'][$result['id']] = [
                'name' => $result['name'],
                'description' => $result['overview'],
                'popularity' => $result['popularity'],
                'votes' => $result['vote_average'] . '@' . $result['vote_count'],
                'airdate' => $result['first_air_date'],
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
        foreach ($this->lookup['series'] as $id => $series) {
            echo "Enriching #$id\n";
            $this->lookup['series'][$id]['details'] = $this->lookupDetails($id);
            // @todo: providers
        }
    }

    public function compile(): void
    {
        file_put_contents(self::CACHE_DIR . DIRECTORY_SEPARATOR . 'compiled.json', json_encode($this->lookup['series'], JSON_PRETTY_PRINT));
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
}

$watchman = new Watchman('2025-11-01', '2025-11-09');
echo "Discovering ...\n";
$watchman->recursiveDiscover(1);
$watchman->iterateSeries();
$watchman->compile();
echo "Done.\n";
