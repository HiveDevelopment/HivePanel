<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CellDomain;
use App\Models\Node;
use App\Services\Node\WebProxyNodeClient;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AdminNodeWebProxyController extends Controller
{
    public function index(Node $node, WebProxyNodeClient $proxy)
    {
        $status = null;
        $error = '';
        try {
            $status = $proxy->request($node, 'GET', '/configuration/web-proxy');
        } catch (ValidationException $e) {
            $error = $e->errors()['domains'][0];
        }

        return Inertia::render('Admin/Nodes/WebProxy', ['node' => ['id' => $node->id, 'name' => $node->name, 'location' => $node->location, 'scheme' => $node->scheme, 'fqdn' => $node->fqdn, 'public_fqdn' => $node->public_fqdn ?: $node->fqdn], 'status' => $status, 'connectionError' => $error]);
    }

    public function update(Node $node, Request $request, WebProxyNodeClient $proxy)
    {
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'bind_ip' => ['required', 'ip'], 'http_port' => ['required', 'integer', 'min:1', 'max:65535'], 'https_port' => ['required', 'integer', 'min:1', 'max:65535', 'different:http_port'], 'dns_address' => ['required', 'string', 'max:253'], 'acme_email' => ['nullable', 'email', 'max:254'], 'tcp_ports' => ['present', 'array', 'max:32'], 'tcp_ports.*' => ['required', 'integer', 'min:1024', 'max:65535', 'distinct'], 'udp_ports' => ['present', 'array', 'max:32'], 'udp_ports.*' => ['required', 'integer', 'min:1024', 'max:65535', 'distinct'], 'trusted_proxies' => ['present', 'array', 'max:16'], 'trusted_proxies.*' => ['required', 'string', 'max:64']]);
        $data['acme_email'] = $data['acme_email'] ?? '';
        $data['image'] = 'nginx:stable';
        $data['certbot_image'] = 'certbot/certbot:latest';
        $proxy->request($node, 'PATCH', '/configuration/web-proxy', $data, true);

        return back()->with('success', 'Node proxy settings applied.');
    }

    public function destroy(Node $node, string $domain, WebProxyNodeClient $proxy)
    {
        $proxy->request($node, 'DELETE', '/configuration/web-proxy/routes/'.$domain);
        CellDomain::where('node_id', $node->id)->whereKey($domain)->delete();

        return back()->with('success', 'Proxy route removed.');
    }
}
