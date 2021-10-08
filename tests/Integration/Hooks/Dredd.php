<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Integration\Hooks;

/**
 * balloon.
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */
use Dredd\Hooks;
use GuzzleHttp;

function get($url, $transaction)
{
    $headers = (array) $transaction->request->headers;
    unset($headers['Content-Type']);

    $client = new GuzzleHttp\Client([
        'base_uri' => $transaction->host.':'.$transaction->port,
        'headers' => $headers,
    ]);

    $response = $client->get($url);
    $body = json_decode($response->getBody()->getContents(), true);

    return $body;
}

function post($url, $record, $transaction)
{
    $client = new GuzzleHttp\Client([
        'base_uri' => $transaction->host.':'.$transaction->port,
        'headers' => (array) $transaction->request->headers,
    ]);

    $response = $client->post($url, [
        'json' => $record,
    ]);

    $body = json_decode($response->getBody()->getContents(), true);

    return $body;
}

function delete($url, $transaction)
{
    $client = new GuzzleHttp\Client([
        'base_uri' => $transaction->host.':'.$transaction->port,
        'headers' => (array) $transaction->request->headers,
    ]);

    $client->delete($url);
}

function put($url, $data, $transaction)
{
    $client = new GuzzleHttp\Client([
        'base_uri' => $transaction->host.':'.$transaction->port,
        'headers' => (array) $transaction->request->headers,
    ]);

    $response = $client->put($url, [
        'body' => $data,
    ]);

    $body = json_decode($response->getBody()->getContents(), true);

    return $body;
}

$id = null;
$user = null;
$auth = '';

Hooks::beforeEach(function (&$transaction) use (&$id, &$auth) {
    $transaction->request->headers->Authorization = $auth;

    if (isset($id[$transaction->origin->resourceGroupName])) {
        $transaction->request->uri = str_replace('5cf767f818bf8e399206a693', $id[$transaction->origin->resourceGroupName], $transaction->request->uri);
        $transaction->fullPath = str_replace('5cf767f818bf8e399206a693', $id[$transaction->origin->resourceGroupName], $transaction->request->uri);
    }
});

/**
 * Create a seaparate account for the integration tests.
 */
Hooks::beforeAll(function (&$transaction) use (&$auth, &$user) {
    $users = get('/api/v2/users?query={"username":"_dredd_integration_test"}', $transaction[0]);

    if ($users['count'] > 0) {
        delete('/api/v2/users/'.$users['data'][0]['id'].'?force=1', $transaction[0]);
    }

    $pw = bin2hex(openssl_random_pseudo_bytes(4));

    $user = post('/api/v2/users', [
        'username' => '_dredd_integration_test',
        'admin' => true,
        'password' => $pw,
    ], $transaction[0]);

    $auth = 'Basic '.base64_encode('_dredd_integration_test:'.$pw);
});

Hooks::afterAll(function (&$transaction) {
    $users = get('/api/v2/users?query={"username":"_dredd_integration_test"}', $transaction[0]);

    if ($users['count'] > 0) {
        delete('/api/v2/users/'.$users['data'][0]['id'].'?force=1', $transaction[0]);
    }
});

Hooks::before('convert.v2 > *', function (&$transaction) {
    $transaction->skip = true;
});

Hooks::before('preview.v2 > *', function (&$transaction) {
    $transaction->skip = true;
});

Hooks::before('wopi.v2 > *', function (&$transaction) {
    $transaction->skip = true;
});

Hooks::before('desktopclient.v2 > *', function (&$transaction) {
    $transaction->skip = true;
});

Hooks::before('feedback.v2 > *', function (&$transaction) {
    $transaction->skip = true;
});

Hooks::before('sharelink.v2 > *', function (&$transaction) {
    $transaction->skip = true;
});

Hooks::before('elasticsearch.v2 > *', function (&$transaction) {
    $transaction->skip = true;
});

Hooks::after('core.v2 > /api/v2/users/{user} > Delete user > 204 > *', function (&$transaction) {
    $transaction->fullPath = $transaction->request->uri;
    delete($transaction->request->uri.'?force=1', $transaction);
});

Hooks::before('core.v2 > /api/v2/files/{file}/restore > Rollback file > 200 > *', function (&$transaction) {
    $file = get('/api/v2/files', $transaction)['data'][0];
    put('/api/v2/files?name='.$file['name'], 'version-2', $transaction);

    $transaction->request->uri .= '&id='.$file['id'];
    $transaction->fullPath = $transaction->request->uri;
});

Hooks::before('core.v2 > /api/v2/tokens > Get OAUTH2 Bearer token > *', function (&$transaction) {
    $transaction->request->headers->Authorization = 'Basic '.base64_encode('balloon-client-web:');
    $transaction->request->body = 'grant_type=password&username=admin&password=admin';
});

Hooks::before('core.v2 > /api/v2/files > Upload file > 201 > *', function (&$transaction) {
    $transaction->request->uri .= '?name=upload-file-201';
    $transaction->request->body = 'bar';
    $transaction->expected->body = '{"name":"bar"}';
    $transaction->fullPath = $transaction->request->uri;
});

Hooks::before('core.v2 > /api/v2/files/chunk > Resumeable upload file (chunked) > 206 > *', function (&$transaction) {
    $transaction->request->uri = '/api/v2/files/chunk?index=1&chunks=2';
    $transaction->fullPath = $transaction->request->uri;
});

Hooks::before('core.v2 > /api/v2/files/chunk > Resumeable upload file (chunked) > 201 > *', function (&$transaction) {
    $transaction->request->uri .= '&name=file-chunk-201';
    $transaction->request->body = 'barfoo';
    $transaction->expected->body = '{"name":"barfoo"}';
    $transaction->fullPath = $transaction->request->uri;
});

Hooks::before('core.v2 > /api/v2/files/chunk > Resumeable upload file (chunked) > 200 > *', function (&$transaction) {
    $file = put('/api/v2/files?name=file-chunk-200', 'version-1', $transaction);

    $transaction->request->body = 'version-2';
    $transaction->request->uri .= '&name=file-chunk-200&index=1&chunks=1&id='.$file['id'];
    $transaction->fullPath = $transaction->request->uri;
});

Hooks::before('core.v2 > /api/v2/nodes/{node}/move > Move node > 200 > *', function (&$transaction) {
    $collection = get('/api/v2/collections', $transaction)['data'][1];

    $transaction->request->uri .= '&destid='.$collection['id'];
    $transaction->request->uri = str_replace('conflict=0', 'conflict=1', $transaction->request->uri);
    $transaction->fullPath = $transaction->request->uri;
});

Hooks::before('core.v2 > /api/v2/nodes/{node}/move > Move node > 207 > *', function (&$transaction) {
    $transaction->skip = true;
});

Hooks::before('core.v2 > /api/v2/nodes/{node}/undelete > Restore node > 207 > *', function (&$transaction) {
    $transaction->skip = true;
});

Hooks::before('core.v2 > /api/v2/nodes/{node}/undelete > Restore node > 200 > *', function (&$transaction) {
    $transaction->request->uri = str_replace('conflict=0', 'conflict=1', $transaction->request->uri);
    $transaction->fullPath = $transaction->request->uri;
});

Hooks::before('core.v2 > /api/v2/nodes/{node}/clone > Clone node > 201 > *', function (&$transaction) {
    $transaction->request->uri = str_replace('conflict=0', 'conflict=1', $transaction->request->uri);
    $transaction->fullPath = $transaction->request->uri;
});

Hooks::before('core.v2 > /api/v2/nodes/{node}/clone > Clone node > 207 > *', function (&$transaction) {
    $transaction->skip = true;
});

Hooks::before('core.v2 > /api/v2/nodes/{node} > Change node > 200 > *', function (&$transaction) {
    $transaction->request->body = '{"name": "foo"}';
});

Hooks::before('core.v2 > /api/v2/collections/{collection}/share > Create share > *', function (&$transaction) {
    $user = get('/api/v2/users', $transaction)['data'][1];

    $resource = json_decode($transaction->expected->body, true);
    $resource['acl'] = [
        [
            'type' => 'user',
            'id' => $user['id'],
            'privilege' => 'r',
        ],
    ];
    $transaction->request->body = json_encode($resource);
});

Hooks::before('notification.v2 > /api/v2/notifications > Send notification > 202 > *', function (&$transaction) use (&$user) {
    $resource = json_decode($transaction->request->body, true);
    $resource['receiver'] = [$user['id']];
    $transaction->request->body = json_encode($resource);
});
Hooks::after('notification.v2 > /api/v2/notifications > Send notification > 202 > *', function (&$transaction) use (&$id) {
    $messages = get('/api/v2/notifications', $transaction)['data'];
    $message = end($messages);
    $id['notification.v2'] = $message['id'];
});

Hooks::before('notification.v2 > /api/v2/nodes/{node}/subscription > Subscribe node updates > 200 > *', function (&$transaction) {
    $node = get('/api/v2/collections', $transaction)['data'][0];
    $transaction->request->uri .= '?id='.$node['id'];
    $transaction->fullPath = $transaction->request->uri;
});

Hooks::afterEach(function (&$transaction) use (&$id) {
    if ($transaction->expected->statusCode == '201') {
        if ($transaction->real->statusCode === 201) {
            $body = json_decode($transaction->real->body);
            $id[$transaction->origin->resourceGroupName] = $body->id;
        }
    }
});
