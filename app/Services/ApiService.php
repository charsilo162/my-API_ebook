<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Session;

class ApiService
{
    protected $client;
    protected $baseUrl = 'http://api.localhost:8000/api';

      public function __construct()
    {
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function withToken()
    {
        $token = Session::get('api_token');
        if ($token) {
            $this->client = new Client([
                'base_uri' => $this->baseUrl,
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ],
            ]);
        }
        return $this;
    }

    public function get($uri, $query = [])
    {
        $response = $this->client->get($uri, ['query' => $query]);
        return json_decode($response->getBody(), true);
    }

    public function post($uri, $data = [], $multipart = false)
    {
        if ($multipart) {
            $response = $this->client->post($uri, ['multipart' => $data]);
        } else {
            $response = $this->client->post($uri, ['json' => $data]);
        }
        return json_decode($response->getBody(), true);
    }

    public function put($uri, $data = [], $multipart = false)
    {
        if ($multipart) {
            $response = $this->client->put($uri, ['multipart' => $data]);
        } else {
            $response = $this->client->put($uri, ['json' => $data]);
        }
        return json_decode($response->getBody(), true);
    }

    public function delete($uri)
    {
        $response = $this->client->delete($uri);
        return json_decode($response->getBody(), true);
    }
}