<?php

declare(strict_types=1);

namespace LabelcampAPI;

use LabelcampAPI\Resource\ResourceObject;
use LabelcampAPI\Resource\ToOneRelationship;
use LabelcampAPI\Resource\ToManyRelationship;

class LabelcampAPI {

    protected string $accessToken = '';
    protected array $lastResponse = [];
    protected array $options = [
        'auto_refresh' => false
    ];
    protected ?Session $session = null;
    protected ?Request $request = null;

    protected string $namespaceId = '';

    /**
     * Constructor
     * Set options and class instances to use.
     *
     * @param array|object $options Optional. Options to set.
     * @param Session $session Optional. The Session object to use.
     * @param Request $request Optional. The Request object to use.
     */
    public function __construct(array|object $options = [], ?Session $session = null, ?Request $request = null) {
        $this->setOptions($options);
        $this->setSession($session);

        $this->request = $request ?? new Request();
    }

    /**
     * Set the access token to use.
     *
     * @param string $accessToken The access token.
     *
     * @return self
     */
    public function setAccessToken(string $accessToken): self {
        $this->accessToken = $accessToken;

        return $this;
    }

    /**
     * Set options
     *
     * @param array|object $options Options to set.
     *
     * @return self
     */
    public function setOptions(array|object $options): self {
        $this->options = array_merge($this->options, (array) $options);

        return $this;
    }

    /**
     * Set the Session object to use.
     *
     * @param Session $session The Session object.
     *
     * @return self
     */
    public function setSession(?Session $session): self {
        $this->session = $session;

        return $this;
    }

    /**
     * Add authorization headers.
     *
     * @param $headers array. Optional. Additional headers to merge with the authorization headers.
     *
     * @return array Authorization headers, optionally merged with the passed ones.
     */
    protected function authHeaders(array $headers = []): array {
        $accessToken = $this->session ? $this->session->getAccessToken() : $this->accessToken;

        if ($accessToken) {
            $headers = array_merge($headers, [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/vnd.api+json',
            ]);
        }

        return $headers;
    }

    /**
     * Get the latest full response from the Dropbox API.
     *
     * @return array Response data.
     * - array body The response body.
     * - array headers Response headers.
     * - int status HTTP status code.
     * - string url The requested URL.
     */
    public function getLastResponse(): array {
        return $this->lastResponse;
    }

    /**
     * Send a request to the Lampcamp API, automatically refreshing the access token as needed.
     *
     * @param string $method The HTTP method to use.
     * @param string $uri The URI to request.
     * @param string|array $arguments
     * @param string|resource  $body
     * @param string|array $parameters Optional. Query string parameters or HTTP body, depending on $method.
     *
     * @throws LabelcampAPIException
     *
     * @return array Response data.
     * - array body The response body.
     * - array headers Response headers.
     * - int status HTTP status code.
     * - string url The requested URL.
     */
    protected function apiRequest(
        string $method,
        string $uri,
        array $parameters = [],
        array $headers = []
    ): array {
        try {
            $headers = $this->authHeaders($headers);

            $options = ['headers' => $headers];
            if (isset($parameters['filter'])) {
                $options['query'] = $parameters;
            } elseif ($parameters) {
                $options['json'] = $parameters;
            }


            return $this->request->api($method, $uri, $options);
        } catch (LabelcampAPIException $e) {
            if ($this->options['auto_refresh'] && $e->hasExpiredToken()) {
                $result = $this->session->refreshAccessToken();

                if (!$result) {
                    throw new LabelcampAPIException('Could not refresh access token.');
                }

                return $this->apiRequest($method, $uri, $parameters, $headers);
            }

            throw $e;
        }
    }

    /**
     * Get Resource Data
     * 
     * @param string $type
     * @param string $id
     * @param array $attributes Optional
     * @param array $relationships Optional
     * @return array
     */
    public function getResource(string $type, string $id = '', array $attributes = [],  array $relationships = []): array {
        $resource = new ResourceObject($type, $id);

        foreach ($attributes as $name => $value) {
            $resource->setAttributes($name, $value);
        }

        foreach ($relationships as $name => $relationship) {
            if (!empty($relationship['type']) && !empty($relationship['id'])) {
                $toone = new ToOneRelationship($relationship['type'], $relationship['id']);
                $resource->setRelationship($name, $toone);
            } else {
                $tomany = new ToManyRelationship();
                foreach ($relationship as $identifier) {
                    $tomany->addResourceIdentifier($identifier['type'], $identifier['id']);
                }
                $resource->setRelationship($name, $tomany);
            }
        }

        return $resource->toArray();
    }

    /**
     *  Get user
     * 
     * @link https://developer.labelcamp.io/resources/user
     * 
     * @param string $user_id
     * @param array $filter
     * 
     * @return array
     */

    public function getUser(string $user_id = '', array $filter = []): array {
        $uri = '/users/' . $user_id;

        $parameters = [
            "filter" => $filter
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Create User
     * 
     * @link https://developer.labelcamp.io/resources/user
     * 
     * @param array $parameters
     * 
     * @return array
     */
    public function createUser(array $parameters): array {
        $uri = '/users';

        $this->lastResponse = $this->apiRequest('POST', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Update User
     * 
     * @link https://developer.labelcamp.io/resources/user
     * 
     * @param string $user_id
     * @param array $parameters
     * 
     * @return array
     */
    public function updateUser(string $user_id, array $parameters): array {
        $uri = '/users/' . $user_id;

        $this->lastResponse = $this->apiRequest('PUT', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Delete User
     * 
     * @link https://developer.labelcamp.io/resources/user
     * 
     * @param string $user_id
     */
    public function deleteUser(string $user_id) {
        $uri = '/users/' . $user_id;

        $this->apiRequest('DELETE', $uri);
    }

    /**
     *  Get List of Dsps
     * 
     * @link https://developer.labelcamp.io/resources/dsp
     * 
     * @param string $dsp_id
     * @param array $filter
     * 
     * @return array
     */

    public function getDspsList(string $dsp_id = '', array $filter = []): array {
        $uri = '/dsps/' . $dsp_id;

        $parameters = [
            "filter" => $filter
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     *  Get Playlist
     * 
     * @link https://developer.labelcamp.io/resources/playlist
     * 
     * @param string $playlist_id
     * 
     * @return array
     */

    public function getPlaylists(string $playlist_id = ''): array {
        $uri = '/playlists/' . $playlist_id;

        $this->lastResponse = $this->apiRequest('GET', $uri);

        return $this->lastResponse['body'];
    }

    /**
     *  Get Artist
     * 
     * @link https://developer.labelcamp.io/resources/artist
     * 
     * @param string $artist_id
     * 
     * @return array
     */

    public function getArtist(string $artist_id = '', array $filter = [], array $page = []): array {
        $uri = '/artists/' . $artist_id;

        $parameters = [
            'filter' => $filter,
            'page' => $page
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     *  Create New Artist
     * 
     * @link https://developer.labelcamp.io/resources/artist
     *
     * @param array $attributes 
     * @param array $relationship 
     * 
     * @return array
     */

    public function createArtist(array $attributes, array $relationship): array {
        $uri = '/artists';

        $request_data = $this->getResource('artists', "", $attributes, $relationship);

        $this->lastResponse = $this->apiRequest('POST', $uri, $request_data);

        return $this->lastResponse['body'];
    }

    /**
     *  Update Artist
     * 
     * @link https://developer.labelcamp.io/resources/artist
     * 
     * @param string $artist_id
     * @param array $attributes
     * 
     * @return array
     */

    public function updateArtist(string $artist_id, array $attributes): array {
        $uri = '/artists/' . $artist_id;

        $request_data = $this->getResource('artists', $artist_id, $attributes);

        $this->lastResponse = $this->apiRequest('PUT', $uri, $request_data);

        return $this->lastResponse['body'];
    }

    /**
     *  Get  All Tracks
     * 
     * @link https://developer.labelcamp.io/resources/track
     * 
     * @param string $track_id
     * @param array $filter
     * 
     * @return array
     */

    public function getTracks(string $track_id = '', array $filter = []): array {
        $uri = '/tracks/' . $track_id;

        $parameters = [
            "filter" => $filter,
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     *  Create New Track
     * 
     * @link https://developer.labelcamp.io/resources/track#create-track
     * 
     * @param array $parameters
     * 
     * @return array
     */

    public function createTrack(array $parameters): array {
        $uri = '/tracks';

        $this->lastResponse = $this->apiRequest('POST', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     *  Update Existed Track
     * 
     * @link https://developer.labelcamp.io/resources/track
     * 
     * @param string $track_id
     * @param array $parameters
     * 
     * @return array
     */

    public function updateTrack(string $track_id, array $parameters): array {
        $uri = '/tracks/' . $track_id;

        $this->lastResponse = $this->apiRequest('PUT', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     *  Delete Track
     * 
     * @link https://developer.labelcamp.io/resources/track#delete-track
     * 
     * @param string $track_id
     * 
     */

    public function deleteTrack(string $track_id) {
        $uri = '/tracks/' . $track_id;

        $this->apiRequest('DELETE', $uri);
    }

    /**
     * Get Companies
     * 
     * @link https://developer.labelcamp.io/resources/company
     * 
     * @param string $company_id
     * @param array $filter
     * 
     * @return array
     */
    public function getCompanies(string $company_id = '', array $filter = []): array {
        $uri = '/companies/' . $company_id;

        $parameters = [
            "filter" => $filter,
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Create Company
     * 
     * @link https://developer.labelcamp.io/resources/company#create-company
     * 
     * @param array $parameters
     * 
     * @return array
     */
    public function createCompanie(array $parameters): array {
        $uri = '/companies';

        $this->lastResponse = $this->apiRequest('POST', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Update Company
     * 
     * @link https://developer.labelcamp.io/resources/company#update-company
     * 
     * @param string $companie_id
     * @param array $parameters
     * 
     * @return array
     */
    public function updateCompanie(string $companie_id, array $parameters): array {
        $uri = '/companies/' . $companie_id;

        $this->lastResponse = $this->apiRequest('POST', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Get Continents
     * 
     * @link https://developer.labelcamp.io/resources/continent
     * 
     * @return array
     */
    public function getContinents(): array {
        $uri = '/continents';

        $this->lastResponse = $this->apiRequest('GET', $uri);

        return $this->lastResponse['body'];
    }

    /**
     * Get Continent By Id
     * 
     * @link https://developer.labelcamp.io/resources/continent#get-continent
     * 
     * @param string $continent_id
     * 
     * @return array
     */
    public function getContinentById(string $continent_id = ''): array {
        $uri = '/continents/' . $continent_id;

        $this->lastResponse = $this->apiRequest('GET', $uri);

        return $this->lastResponse['body'];
    }

    /**
     * Get Currencies
     * 
     * @link https://developer.labelcamp.io/resources/currencies
     * 
     * @param string $currency_id
     * 
     * @return array
     */
    public function getCurrencies(string $currency_id = ''): array {
        $uri = '/currencies/' . $currency_id;

        $this->lastResponse = $this->apiRequest('GET', $uri);

        return $this->lastResponse['body'];
    }

    /**
     * Get Distributor
     * 
     * @link https://developer.labelcamp.io/resources/distributor
     * 
     * @param string $distributor_id
     * @param array $filter
     * 
     * @return array
     */
    public function getDistributor(string $distributor_id = '', array $filter = []): array {
        $uri = '/distributors/' . $distributor_id;

        $parameters = [
            "filter" => $filter,
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Get Distributor Price Code
     * 
     * @link https://developer.labelcamp.io/resources/distributorpricecode
     * 
     * @param string $distributor_price_code_id
     * @param array $filter
     * 
     * @return array
     */
    public function getDistributorPriceCode(string $distributor_price_code_id = '', array $filter = []): array {
        $uri = '/distributor-price-codes/' . $distributor_price_code_id;

        $parameters = [
            "filter" => $filter,
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Get Distributors Product Sub Genres
     * 
     * @link https://developer.labelcamp.io/resources/distributorproductsubgenres
     * 
     * @param string $distributor_product_sub_genres_id
     * @param array $filter
     * 
     * @return array
     */
    public function getDistributorProductSubGenres(string $distributor_product_sub_genres_id = '', array $filter = []): array {
        $uri = '/distributor-product-sub-genres/' . $distributor_product_sub_genres_id;

        $parameters = [
            "filter" => $filter,
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Get DSP
     * 
     * @link https://developer.labelcamp.io/resources/dsp
     * 
     * @param string $dsp_id
     * @param array $filter
     * 
     * @return array
     */
    public function getDsp(string $dsp_id = '', array $filter = []): array {
        $uri = '/dsps/' . $dsp_id;

        $parameters = [
            "filter" => $filter,
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Get Dsp State
     * 
     * @link https://developer.labelcamp.io/resources/dspstate
     * 
     * @param string $dsp_state_id
     * @param array $filter
     * 
     * @return array
     */
    public function getDspState(string $dsp_state_id = '', array $filter = []): array {
        $uri = '/dsp-states/' . $dsp_state_id;

        $parameters = [
            "filter" => $filter
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Create Dsp State
     * 
     * @link https://developer.labelcamp.io/resources/dspstate
     * 
     * @param array $parameters 
     * 
     * @return array
     */
    public function createDspState(array $parameters): array {
        $uri = '/dsp-states';

        $this->lastResponse = $this->apiRequest('POST', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Get Gender
     * 
     * @link https://developer.labelcamp.io/resources/gender
     * 
     * @param string $gender_id
     * 
     * @return array
     */
    public function getGender(string $gender_id = ''): array {
        $uri = '/genders/' . $gender_id;

        $this->lastResponse = $this->apiRequest('GET', $uri);

        return $this->lastResponse['body'];
    }

    /**
     * Get Groups
     * 
     * @link https://developer.labelcamp.io/resources/group
     * 
     * @param string $group_id
     * @param array $filter
     * 
     * @return array
     */
    public function getGroup(string $group_id = '', array $filter = []): array {
        $uri = '/groups/' . $group_id;

        $parameters = [
            "filter" => $filter,
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Create New Group
     * 
     * @link https://developer.labelcamp.io/resources/group
     * 
     * @param array $parameters
     * 
     * @return array
     */
    public function createGroup(array $parameters): array {
        $uri = '/groups';

        $this->lastResponse = $this->apiRequest('POST', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Update Group
     * 
     * @link https://developer.labelcamp.io/resources/group
     * 
     * @param string $group_id
     * @param array $parameters
     * 
     * @return array
     */
    public function updateGroup(string $group_id, array $parameters): array {
        $uri = '/groups/' . $group_id;

        $this->lastResponse = $this->apiRequest('PUT', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Delete Group
     * 
     * @link https://developer.labelcamp.io/resources/group
     * 
     * @param string $group_id
     */
    public function deleteGroup(string $group_id) {
        $uri = '/groups/' . $group_id;

        $this->apiRequest('DELETE', $uri);
    }

    /**
     * Get Import Tasks
     * 
     * @link https://developer.labelcamp.io/resources/importtask
     * 
     * @param string $import_task_id
     * @param array $filter
     * 
     * @return array
     */
    public function getImportTask(string $import_task_id = '', array $filter = []): array {
        $uri = '/import-tasks/' . $import_task_id;

        $parameters = [
            "filter" => $filter
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Create Import Task
     * 
     * @link https://developer.labelcamp.io/resources/importtask
     * 
     * @param array $parameters
     * 
     * @return array
     */
    public function createImportTask(array $parameters): array {
        $uri = '/import-tasks';

        $this->lastResponse = $this->apiRequest('POST', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Get Labels
     * 
     * @link https://developer.labelcamp.io/resources/label
     * 
     * @param string $label_id
     * @param array $filter
     * 
     * @return array
     */
    public function getLabel(string $label_id = '', array $filter = []): array {
        $uri = '/labels/' . $label_id;

        $parameters = [
            "filter" => $filter,
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Create Lable
     * 
     * @link https://developer.labelcamp.io/resources/label
     * 
     * @param array $parameters
     * 
     * @return array
     */
    public function createLabel(array $parameters): array {
        $uri = '/labels';

        $this->lastResponse = $this->apiRequest('POST', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Update Lable
     * 
     * @link https://developer.labelcamp.io/resources/label
     * 
     * @param string $label_id
     * @param array $parameters
     * 
     * @return array
     */
    public function updateLabel(string $label_id, array $parameters): array {
        $uri = '/labels';

        $this->lastResponse = $this->apiRequest('PUT', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Delete Label
     * 
     * @link https://developer.labelcamp.io/resources/label
     * 
     * @param string $label_id
     */
    public function deleteLabel(string $label_id) {
        $uri = '/labels/' . $label_id;

        $this->apiRequest('DELETE', $uri);
    }

    /**
     * Get Languages
     * 
     * @link https://developer.labelcamp.io/resources/language
     * 
     * @param string $language_id
     * 
     * @return array
     */
    public function getLanguage(string $language_id = ''): array {
        $uri = '/languages/' . $language_id;

        $this->lastResponse = $this->apiRequest('GET', $uri);

        return $this->lastResponse['body'];
    }

    /**
     * Get Offer
     * 
     * @link https://developer.labelcamp.io/resources/offer
     * 
     * @param string $offer_id
     * @param array $filter
     * 
     * @return array
     */
    public function getOffer(string $offer_id = '', array $filter = []): array {
        $uri = '/offers/' . $offer_id;

        $parameters = [
            "filter" => $filter,
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Create Offer
     * 
     * @link https://developer.labelcamp.io/resources/offer
     * 
     * @param array $parameters
     * 
     * @return array
     */
    public function createOffer(array $parameters): array {
        $uri = '/offers';

        $this->lastResponse = $this->apiRequest('POST', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Update Offer
     * 
     * @link https://developer.labelcamp.io/resources/offer
     * 
     * @param string $offer_id
     * @param array $parameters
     * 
     * @return array
     */
    public function updateOffer(string $offer_id, array $parameters): array {
        $uri = '/offers' . $offer_id;

        $this->lastResponse = $this->apiRequest('POST', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Delete Offer
     * 
     * @link https://developer.labelcamp.io/resources/offer
     * 
     * @param string $offer_id
     */
    public function deleteOffer(string $offer_id) {
        $uri = '/offers/' . $offer_id;

        $this->apiRequest('DELETE', $uri);
    }

    /**
     * Get Products
     * 
     * @link https://developer.labelcamp.io/resources/product
     * 
     * @param string $product_id
     * @param array $filter
     * 
     * @return array
     */
    public function getProduct(string $product_id = '', array $filter = []): array {
        $uri = '/products/' . $product_id;

        $parameters = [
            "filter" => $filter,
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Create Product 
     * 
     * @link https://developer.labelcamp.io/resources/product
     * 
     * @param array $parameters
     * 
     * @return array
     */
    public function createProduct(array $parameters): array {
        $uri = '/products';

        $this->lastResponse = $this->apiRequest('POST', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Update Product
     * 
     * @link https://developer.labelcamp.io/resources/product
     * 
     * @param string $product_id
     * @param array $parameters
     * 
     * @return array
     */
    public function updateProduct(string $product_id, array $parameters): array {
        $uri = '/products/' . $product_id;

        $this->lastResponse = $this->apiRequest('PUT', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Delete Product
     * 
     * @link https://developer.labelcamp.io/resources/product
     * 
     * @param string $product_id
     */
    public function deleteProduct(string $product_id) {
        $uri = '/products/' . $product_id;

        $this->apiRequest('DELETE', $uri);
    }

    /**
     * Get Playlist Genres
     * 
     * @link https://developer.labelcamp.io/resources/productgenre
     * 
     * @param string $product_genre_id
     * 
     * @return array
     */
    public function getProductGenre(string $product_genre_id = ''): array {
        $uri = '/product-genres/' . $product_genre_id;

        $this->lastResponse = $this->apiRequest('GET', $uri);

        return $this->lastResponse['body'];
    }

    /**
     * Get Product Type
     * 
     * @link https://developer.labelcamp.io/resources/producttype
     * 
     * @param string $product_type_id
     * 
     * @return array
     */
    public function getProductType(string $product_type_id = ''): array {
        $uri = '/product-types/' . $product_type_id;

        $this->lastResponse = $this->apiRequest('GET', $uri);

        return $this->lastResponse['body'];
    }

    /**
     * Get Quotas
     * 
     * @link https://developer.labelcamp.io/resources/quotas
     * 
     * @return array
     */
    public function getQuotas(): array {
        $uri = '/quotas';

        $this->lastResponse = $this->apiRequest('GET', $uri);

        return $this->lastResponse['body'];
    }

    /**
     * Get Records
     * 
     * @link https://developer.labelcamp.io/resources/record
     * 
     * @param string $record_id
     * @param array $filter
     * 
     * @return array
     */
    public function getRecord(string $record_id = '', array $filter = []): array {
        $uri = '/records/' . $record_id;

        $parameters = [
            "filter" => $filter,
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Create Record
     * 
     * @link https://developer.labelcamp.io/resources/record
     * 
     * @param array $parameters
     * 
     * @return array
     */
    public function createRecord(array $parameters): array {
        $uri = '/records';

        $this->lastResponse = $this->apiRequest('POST', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Update Record
     * 
     * @link https://developer.labelcamp.io/resources/record
     * 
     * @param string $record_id
     * @param array $parameters
     * 
     * @return array
     */
    public function updateRecord(string $record_id, array $parameters): array {
        $uri = '/records/' . $record_id;

        $this->lastResponse = $this->apiRequest('PUT', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Delete Record
     * 
     * @link https://developer.labelcamp.io/resources/record
     * 
     * @param string $record_id
     * 
     */
    public function deleteRecord(string $record_id) {
        $uri = '/records/' . $record_id;

        $this->apiRequest('DELETE', $uri);
    }

    /**
     * Get Retail
     * 
     * @link https://developer.labelcamp.io/resources/retail
     * 
     * @param string $retail_id
     * 
     * @return array
     */
    public function getRetail(string $retail_id = ''): array {
        $uri = '/retails/' . $retail_id;

        $this->lastResponse = $this->apiRequest('GET', $uri);

        return $this->lastResponse['body'];
    }

    /**
     * Get Rights
     * 
     * @link https://developer.labelcamp.io/resources/right
     * 
     * @param string $right_id
     * @param array $filter
     * 
     * @return array
     */
    public function getRights(string $right_id = '', array $filter = []): array {
        $uri = '/rights/' . $right_id;

        $parameters = [
            "filter" => $filter,
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Create Right
     * 
     * @link https://developer.labelcamp.io/resources/right
     * 
     * @param array $parameters
     * 
     * @return array
     */
    public function createRight(array $parameters): array {
        $uri = '/rights';

        $this->lastResponse = $this->apiRequest('POST', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Update Right
     * 
     * @link https://developer.labelcamp.io/resources/right
     * 
     * @param string $right_ids
     * @param array $parameters
     * 
     * @return array
     */
    public function updateRight(string $right_id, array $parameters): array {
        $uri = '/rights/' . $right_id;

        $this->lastResponse = $this->apiRequest('PUT', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Delete Right
     * 
     * @link https://developer.labelcamp.io/resources/right
     * 
     * @param string $right_id
     */
    public function deleteRight(string $right_id) {
        $uri = '/rights/' . $right_id;

        $this->apiRequest('DELETE', $uri);
    }

    /**
     * Get Send Task
     * 
     * @link https://developer.labelcamp.io/resources/send-task
     * 
     * @param string $send_task_id
     * @param array $filter 
     * 
     * @return array
     */
    public function getSendTask(string $send_task_id = '', array $filter = []): array {
        $uri = '/send-tasks/' . $send_task_id;

        $parameters = [
            "filter" => $filter,
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Create Send Task
     * 
     * @link https://developer.labelcamp.io/resources/send-task
     * 
     * @param array $parameters
     * 
     * @return array
     */
    public function createSendTask(array $parameters): array {
        $uri = '/send-tasks';

        $this->lastResponse = $this->apiRequest('POST', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Update Send Task
     * 
     * @link https://developer.labelcamp.io/resources/send-task
     * 
     * @param string $send_task_id
     * @param array $parameters
     * 
     * @return array
     */
    public function updateSendTask(string $send_task_id, array $parameters): array {
        $uri = '/send-tasks/' . $send_task_id;

        $this->lastResponse = $this->apiRequest('PUT', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Get Send Task Factory
     * 
     * @link https://developer.labelcamp.io/resources/sendtaskfactory
     * 
     * @param string $send_task_factory_id
     * @param array $filter
     * 
     * @return array
     */
    public function getSendTaskFactory(string $send_task_factory_id = '', array $filter = []): array {
        $uri = '/send-task-factories/' . $send_task_factory_id;

        $parameters = [
            "filter" => $filter,
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Create Send Task Factory
     * 
     * @link https://developer.labelcamp.io/resources/sendtaskfactory
     * 
     * @param array $parameters
     * 
     * @return array
     */
    public function createSendTaskFactory(array $parameters): array {
        $uri = '/send-task-factories';

        $this->lastResponse = $this->apiRequest('POST', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Get Spotify Artist
     * 
     * @link https://developer.labelcamp.io/resources/spotifyartist
     * 
     * @param array $filter
     * 
     * @return array
     */
    public function getSpotifyArtist(array $filter = []): array {
        $uri = '/spotify-artists';

        $parameters = [
            "filter" => $filter
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Get Tag
     * 
     * @link https://developer.labelcamp.io/resources/tag
     * 
     * @param string $tag_id
     * 
     * @return array
     */
    public function getTag(string $tag_id = ''): array {
        $uri = '/tags/' . $tag_id;

        $this->lastResponse = $this->apiRequest('GET', $uri);

        return $this->lastResponse['body'];
    }

    /**
     * Get Territory
     * 
     * @link https://developer.labelcamp.io/resources/territory
     * 
     * @param string $territory_id
     * @param array $filter
     * 
     * @return array
     */
    public function getTerritory(string $territory_id = '', array $filter = []): array {
        $uri = '/territories/' . $territory_id;

        $parameters = [
            "filter" => $filter,
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Get Track Offer
     * 
     * @link https://developer.labelcamp.io/resources/trackoffer
     * 
     * @param string $track_offer_id
     * @param array $filter
     * 
     * @return array
     */
    public function getTrackOffer(string $track_offer_id = '', array $filter = []): array {
        $uri = '/track-offers/' . $track_offer_id;

        $parameters = [
            "filter" => $filter,
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Create Track Offer
     * 
     * @link https://developer.labelcamp.io/resources/trackoffer
     * 
     * @param array $parameters
     * 
     * @return array
     */
    public function createTrackOffer(array $parameters): array {
        $uri = '/track-offers';

        $this->lastResponse = $this->apiRequest('POST', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Update Track Offer
     * 
     * @link https://developer.labelcamp.io/resources/trackoffer
     * 
     * @param string $track_offer_id
     * @param array $parameters
     * 
     * @return array
     */
    public function updateTrackOffer(string $track_offer_id, array $parameters): array {
        $uri = '/track-offers/' . $track_offer_id;

        $this->lastResponse = $this->apiRequest('PUT', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Delete Track Offer
     * 
     * @link https://developer.labelcamp.io/resources/trackoffer
     * 
     * @param string $track_offer_id
     */
    public function deleteTrackOffer(string $track_offer_id) {
        $uri = '/track-offers/' . $track_offer_id;

        $this->apiRequest('DELETE', $uri);
    }

    /**
     * Get Track Video
     * 
     * @link https://developer.labelcamp.io/resources/trackvideo
     * 
     * @param string $track_video_id
     * @param array $filter
     * 
     * @return array
     */
    public function getTrackVideo(string $track_video_id = '', array $filter = []): array {
        $uri = '/track-videos/' . $track_video_id;

        $parameters = [
            "filter" => $filter,
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Create Track Video
     * 
     * @link https://developer.labelcamp.io/resources/trackvideo
     * 
     * @param array $parameters
     * 
     * @return array
     */
    public function createTrackVideo(array $parameters): array {
        $uri = '/track-videos';

        $this->lastResponse = $this->apiRequest('POST', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Update Track Video
     * 
     * @link https://developer.labelcamp.io/resources/trackvideo
     * 
     * @param string $track_video_id
     * @param array $parameters
     * 
     * @return array
     */
    public function updateTrackVideo(string $track_video_id, array $parameters): array {
        $uri = '/track-videos/' . $track_video_id;

        $this->lastResponse = $this->apiRequest('PUT', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Delete Track Video
     * 
     * @link https://developer.labelcamp.io/resources/trackvideo
     * 
     * @param string $track_video_id
     */
    public function deleteTrackVideo(string $track_video_id) {
        $uri = '/track-videos/' . $track_video_id;

        $this->apiRequest('DELETE', $uri);
    }

    /**
     * Get Videos
     * 
     * @link https://developer.labelcamp.io/resources/video
     * 
     * @param string $video_id
     * @param array $filter
     * 
     * @return array
     */
    public function getVideos(string $video_id = '', array $filter = []): array {
        $uri = '/videos/' . $video_id;

        $parameters = [
            "filter" => $filter,
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Create Video
     * 
     * @link https://developer.labelcamp.io/resources/video
     * 
     * @param array $parameters
     * 
     * @return array
     */
    public function createVideo(array $parameters): array {
        $uri = '/videos';

        $this->lastResponse = $this->apiRequest('POST', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Update Video
     * 
     * @link https://developer.labelcamp.io/resources/video
     * 
     * @param string $video_id
     * @param array $parameters
     * 
     * @return array
     */
    public function updateVideo(string $video_id, array $parameters): array {
        $uri = '/videos/' . $video_id;

        $this->lastResponse = $this->apiRequest('PUT', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Delete Video
     * 
     * @link https://developer.labelcamp.io/resources/video
     * 
     * @param string $video_id
     */
    public function deleteVideo(string $video_id) {
        $uri = '/videos/' . $video_id;

        $this->apiRequest('DELETE', $uri);
    }

    /**
     * Get Webhooks
     * 
     * @link https://developer.labelcamp.io/resources/webhooks
     * 
     * @param string $webhook_id
     * 
     * @return array
     */
    public function getWebhooks(string $webhook_id = ''): array {
        $uri = '/webhooks/' . $webhook_id;

        $this->lastResponse = $this->apiRequest('GET', $uri);

        return $this->lastResponse['body'];
    }

    /**
     * Create Webhook
     * 
     * @link https://developer.labelcamp.io/resources/webhooks
     * 
     * @param array $parameters
     * 
     * @return array
     */
    public function createWebhook(array $parameters): array {
        $uri = '/webhooks';

        $this->lastResponse = $this->apiRequest('POST', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Delete Webhook
     * 
     * @link https://developer.labelcamp.io/resources/webhooks
     * 
     * @param string $webhook_id
     */
    public function deleteWebhook(string $webhook_id) {
        $uri = '/webhooks/' . $webhook_id;

        $this->apiRequest('DELETE', $uri);
    }

    /**
     * Get Appel Artist
     * 
     * @link https://developer.labelcamp.io/resources/appleartist
     * 
     * @param string $apple_artist_id
     * 
     * @return array
     */
    public function getAppleArtist(string $apple_artist_id = ''): array {
        $uri = '/apple-artists/' . $apple_artist_id;

        $this->lastResponse = $this->apiRequest('GET', $uri);

        return $this->lastResponse['body'];
    }

    /**
     * Create Apple Artist
     * 
     * @link https://developer.labelcamp.io/resources/appleartist
     * 
     * @param array $parameters
     * 
     * @return array
     */
    public function createAppleArtist(array $parameters): array {
        $uri = '/apple-artists';

        $this->lastResponse = $this->apiRequest('POST', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Get Availability
     * 
     * @link https://developer.labelcamp.io/resources/availability
     * 
     * @param string $availability_id
     * @param array $filter
     * 
     * @return array
     */
    public function getAvailability(string $availability_id = '', array $filter = []): array {
        $uri = '/availabilities/' . $availability_id;

        $parameters = [
            "filter" => $filter
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Create Availability
     * 
     * @link https://developer.labelcamp.io/resources/availability
     * 
     * @param array $parameters
     * 
     * @return array
     */
    public function createAvailability(array $parameters): array {
        $uri = '/availabilities';

        $this->lastResponse = $this->apiRequest('POST', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Update Availability
     * 
     * @link https://developer.labelcamp.io/resources/availability
     * 
     * @param string $availability_id
     * @param array $parameters
     * 
     * @return array
     */
    public function updateAvailability(string $availability_id, array $parameters): array {
        $uri = '/availabilities/' . $availability_id;

        $this->lastResponse = $this->apiRequest('PUT', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Delete Availability
     * 
     * @link https://developer.labelcamp.io/resources/availability
     * 
     * @param string $availability_id
     * 
     */
    public function deleteAvailability(string $availability_id) {
        $uri = '/availabilities/' . $availability_id;

        $this->apiRequest('DELETE', $uri);
    }

    /**
     * Create Booklet
     * 
     * @link https://developer.labelcamp.io/resources/booklet
     * 
     * @param array $parameters
     * 
     * @return array
     */
    public function createBooklet(array $parameters): array {
        $uri = '/booklet';

        $this->lastResponse = $this->apiRequest('POST', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Get Customisation
     * 
     * @link https://developer.labelcamp.io/resources/customisation
     * 
     * @return array
     */
    public function getCustomisation(): array {
        $uri = '/customisations';

        $this->lastResponse = $this->apiRequest('GET', $uri);

        return $this->lastResponse['body'];
    }

    /** 
     * Get Dsptag
     * 
     * @link https://developer.labelcamp.io/resources/dsptag
     * 
     * @param string $dsp_tag_id
     * @param array $filter
     * 
     * @return array
     */
    public function getDspTag(string $dsp_tag_id = '', array $filter = []): array {
        $uri = '/dsp-tags/' . $dsp_tag_id;

        $parameters = [
            "filter" => $filter
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }

    /**
     * Get Dsp Upload Identification
     * 
     * @link https://developer.labelcamp.io/resources/dspuploadidentification
     * 
     * @param string $dsp_upload_identification_id
     * @param array $filter
     * 
     * @return array
     */
    public function getDspUploadIdentification(string $dsp_upload_identification_id = '', array $filter = []): array {
        $uri = '/dsp-upload-identifications/' . $dsp_upload_identification_id;

        $parameters = [
            "filter" => $filter
        ];

        $this->lastResponse = $this->apiRequest('GET', $uri, $parameters);

        return $this->lastResponse['body'];
    }
}
