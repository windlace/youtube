<?php

namespace Cast\YouTube;

class Client
{
    const PRIVACY_STATUS_PUBLIC   = 'public';
    const PRIVACY_STATUS_UNLISTED = 'unlisted';
    const PRIVACY_STATUS_PRIVATE  = 'private';
    const CATEGORY_PEOPLE_AND_BLOGS = 22;
    const CHUNK_DELAY = 2;
    /** @var \Google_Client */
    protected $client;
    /** @var \Google_Service_YouTube */
    protected $service;
    protected static $credentialsFilePath;
    protected function __construct()
    {
        $this->setup();
        return $this;
    }
    protected function setup()
    {
        self::$credentialsFilePath = dirname(__DIR__,4) . ($_ENV['CAST_YOUTUBE_CREDENTIALS_FILE_PATH'] ?? die ('missing env variable CAST_YOUTUBE_CREDENTIALS_FILE_PATH'));
        $this->client = new \Google_Client([
            'client_id'     => $_ENV['CAST_YOUTUBE_GOOGLE_OAUTH_CLIENT_ID'] ?? die('missing env variable CAST_YOUTUBE_GOOGLE_OAUTH_CLIENT_ID'),
            'client_secret' => $_ENV['CAST_YOUTUBE_GOOGLE_OAUTH_CLIENT_SECRET'] ?? die('missing env variable CAST_YOUTUBE_GOOGLE_OAUTH_CLIENT_SECRET'),
            'redirect_uri'  => $_ENV['CAST_YOUTUBE_GOOGLE_OAUTH_REDIRECT_URI'] ?? die('missing env variable CAST_YOUTUBE_GOOGLE_OAUTH_REDIRECT_URI'),
        ]);
        $this->client->setAccessType('offline');
        $this->client->setScopes([\Google_Service_YouTube::YOUTUBE]);
        $this->service = new \Google_Service_YouTube($this->client);
    }
    protected function storeCredentials($credentials)
    {
        file_put_contents(self::$credentialsFilePath, json_encode($credentials, JSON_PRETTY_PRINT));
    }
    protected function loadCredentials($assoc = true)
    {
        return json_decode(file_get_contents(self::$credentialsFilePath), $assoc);
    }
    public static function createAuthUrl()
    {
        $youtube = new self;
        return $youtube->client->createAuthUrl();
    }
    public static function auth($code)
    {
        $youtube = new self;
        $credentials = $youtube->client->authenticate($code);
        $youtube->storeCredentials($credentials);
    }
    public static function upload(string $filePath, $properties, $part = 'snippet,status', $params = [])
    {
        $youtube = new self;
        $credentials = $youtube->loadCredentials();
        $youtube->client->setAccessToken($credentials);
        !$youtube->client->isAccessTokenExpired() or $youtube->tokenRefresh();
        $response = $youtube->videoInsert($filePath, $properties, $part, $params);
        return $response;
    }
    protected function tokenRefresh()
    {
        $this->client->fetchAccessTokenWithRefreshToken();
        $credentials = $this->client->getAccessToken();
        $this->storeCredentials($credentials);
    }
    protected function videoInsert($filePath, $properties, $part, $params = [])
    {
        $params = array_filter($params);
        $propertyObject = $this->createResource($properties);
        $resource = new \Google_Service_YouTube_Video($propertyObject);
        $this->client->setDefer(true);
        $request = $this->service->videos->insert($part, $resource, $params);
        $this->client->setDefer(false);
        $response = $this->uploadMedia($request, $filePath, 'video/*');
        return $response;
    }
    protected function createResource($properties)
    {
        $resource = array();
        foreach ($properties as $prop => $value) {
            if ($value) {
                $this->addPropertyToResource($resource, $prop, $value);
            }
        }
        return $resource;
    }
    protected function addPropertyToResource(&$ref, $property, $value) {
        $keys = explode(".", $property);
        $is_array = false;
        foreach ($keys as $key) {
            // Convert a name like "snippet.tags[]" to "snippet.tags" and
            // set a boolean variable to handle the value like an array.
            if (substr($key, -2) == "[]") {
                $key = substr($key, 0, -2);
                $is_array = true;
            }
            $ref = &$ref[$key];
        }

        // Set the property value. Make sure array values are handled properly.
        if ($is_array && $value) {
            $ref = $value;
            $ref = explode(",", $value);
        } elseif ($is_array) {
            $ref = array();
        } else {
            $ref = $value;
        }
    }
    protected function uploadMedia($request, $filePath, $mimeType)
    {
        $chunkSizeBytes = 1 * 1024 * 1024;

        $media = new \Google_Http_MediaFileUpload(
            $this->client,
            $request,
            $mimeType,
            null,
            true,
            $chunkSizeBytes
        );
        $media->setFileSize($fileSize = filesize($filePath));

        $status = false;
        $handle = fopen($filePath, "rb");
        echo "\n";
        $i = 0;
        $chunksCount = intdiv($fileSize, $chunkSizeBytes) + (($fileSize % $chunkSizeBytes) ? 1 : 0);
        while (!$status && !feof($handle)) {
            echo "\rUploading... [{$filePath}] - " . (++$i) . '/'.$chunksCount;
            sleep(self::CHUNK_DELAY);
            $chunk = fread($handle, $chunkSizeBytes);
            $status = $media->nextChunk($chunk);
        }

        fclose($handle);
        return $status;
    }
    protected function categories()
    {
        return [
            1  => 'Film & Animation',
            2  => 'Autos & Vehicles',
            10 => 'Music',
            15 => 'Pets & Animals',
            17 => 'Sports',
            18 => 'Short Movies',
            19 => 'Travel & Events',
            20 => 'Gaming',
            21 => 'Videoblogging',
            22 => 'People & Blogs',
            23 => 'Comedy',
            24 => 'Entertainment',
            25 => 'News & Politics',
            26 => 'Howto & Style',
            27 => 'Education',
            28 => 'Science & Technology',
            29 => 'Nonprofits & Activism',
            30 => 'Movies',
            31 => 'Anime/Animation',
            32 => 'Action/Adventure',
            33 => 'Classics',
            34 => 'Comedy',
            35 => 'Documentary',
            36 => 'Drama',
            37 => 'Family',
            38 => 'Foreign',
            39 => 'Horror',
            40 => 'Sci-Fi/Fantasy',
            41 => 'Thriller',
            42 => 'Shorts',
            43 => 'Shows',
            44 => 'Trailers',
        ];
    }
    protected function defaultParams()
    {
        return [
//            'snippet.categoryId' => YouTube::CATEGORY_PEOPLE_AND_BLOGS,
//            'snippet.defaultLanguage' => '',
//            'snippet.description' => 'Description of uploaded video.',
//            'snippet.tags[]' => '',
//            'snippet.title' => 'Test video upload',
//            'status.embeddable' => '',
//            'status.license' => '',
//            'status.privacyStatus' => YouTube::PRIVACY_STATUS_PUBLIC,
//            'status.publicStatsViewable' => '',
        ];
    }
}

