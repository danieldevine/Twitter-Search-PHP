<?php

namespace Coderjerk\BirdElephant;

use GuzzleHttp\Client;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use GuzzleHttp\HandlerStack;

/**
 * Handles http requests to the Twitter API.
 *
 * @author Dan Devine <dandevine0@gmail.com>
 */
class Request
{
    protected $credentials;

    protected $base_uri = 'https://api.twitter.com/';


    public function __construct($credentials)
    {
        $this->credentials = $credentials;
    }

    public function authorisedRequest($http_method, $path, $params, $data = null, $stream = false, $signed = false, $version = '2')
    {
        return $signed === false ? $this->bearerTokenRequest($http_method, $path, $params, $data, $stream, $version) : $this->userContextRequest($http_method, $path, $params, $data, $stream, $version);
    }

    /**
     * OAuth 2 bearer token request
     *
     * @param string $http_method
     * @param string $path
     * @param array $params
     * @param array $data
     * @param boolean $stream
     *
     * @return object|exception
     */
    public function bearerTokenRequest($http_method, $path, $params, $data = null, $stream = false, $version = '2')
    {
        $bearer_token = $this->credentials['bearer_token'];

        $client = new Client([
            'base_uri' => $this->base_uri . $version . '/'
        ]);

        try {
            $headers = [
                'Authorization' => 'Bearer ' . $bearer_token,
                'Accept'        => 'application/json',
            ];

            //thanks to Guzzle's lack of flexibility with url encoding we have to manually set up the query to preserve colons.
            if ($params) {
                $params = http_build_query($params);
                $path = $path . '?' . str_replace('%3A', ':', $params);
            }

            $request  = $client->request($http_method, $path, [
                'headers' => $headers,
                'json'    => $data ? $data : null,
                'stream'  => $stream === true ? true : false
            ]);

            //if we're streaming the response, echo otherwise return
            if ($stream === true) {
                $body = $request->getBody();
                while (!$body->eof()) {
                    echo json_decode($body->read(1300));
                }
            } else {
                $body = $request->getBody()->getContents();
                $response = json_decode($body);

                return $response;
            }
        } catch (ClientException $e) {
            return $e->getResponse()->getBody()->getContents();
        } catch (ServerException $e) {
            return $e->getResponse()->getBody()->getContents();
        }
    }

    /**
     * Signed requests for logged in users
     * using OAuth 1
     *
     * @param array $credentials
     * @param string $http_method
     * @param string $path
     * @param array $params
     * @param array $data
     * @param boolean $stream
     * @return object|exception
     */
    public function userContextRequest($http_method, $path, $params, $data = null, $stream = false, $version = '2')
    {
        $path = $this->base_uri . $version . '/' . $path;


        $stack = HandlerStack::create();

        $middleware = new Oauth1([
            'consumer_key'    => $this->credentials['consumer_key'],
            'consumer_secret' => $this->credentials['consumer_secret'],
            'token'           => $this->credentials['token_identifier'],
            'token_secret'    => $this->credentials['token_secret']
        ]);

        $stack->push($middleware);

        $client = new Client([
            'base_uri' => $this->base_uri . $version . '/',
            'handler' => $stack
        ]);

        try {
            $request  = $client->request($http_method, $path, [
                'auth' => 'oauth',
                'query' => $params,
                'json' => $data,
                // 'debug' => true
            ]);

            //if we're streaming the response, echo otherwise return
            if ($stream === true) {

                $body = $request->getBody();
                while (!$body->eof()) {
                    echo json_decode($body->read(1300));
                }
            } else {
                $body = $request->getBody()->getContents();
                $response = json_decode($body);

                return $response;
            }
        } catch (ClientException $e) {
            return $e->getResponse()->getBody()->getContents();
        } catch (ServerException $e) {
            return $e->getResponse()->getBody()->getContents();
        }
    }

    public function uploadMedia($media)
    {
        $stack = HandlerStack::create();

        $middleware = new Oauth1([
            'consumer_key'    => $this->credentials['consumer_key'],
            'consumer_secret' => $this->credentials['consumer_secret'],
            'token'           => $this->credentials['token_identifier'],
            'token_secret'    => $this->credentials['token_secret']
        ]);

        $stack->push($middleware);

        $client = new Client([
            'base_uri' => 'https://upload.twitter.com/1.1/',
            'handler' => $stack
        ]);

        try {
            $request  = $client->request('POST', 'media/upload.json', [
                'auth' => 'oauth',
                'multipart' => [
                    [
                        'name'     => 'media_data',
                        'contents' => base64_encode(file_get_contents($media))
                    ]
                ]
            ]);

            $body = $request->getBody()->getContents();
            $response = json_decode($body);
            return $response;
        } catch (ClientException $e) {
            return $e->getResponse()->getBody()->getContents();
        } catch (ServerException $e) {
            return $e->getResponse()->getBody()->getContents();
        }
    }
}
