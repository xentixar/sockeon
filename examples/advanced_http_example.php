<?php
/**
 * Advanced HTTP Example
 * 
 * Demonstrates the enhanced Request and Response classes with
 * optimization features and additional methods
 * 
 * @package     Sockeon\Sockeon
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

require __DIR__ . '/../vendor/autoload.php';

use Sockeon\Sockeon\Core\Server;
use Sockeon\Sockeon\Core\Contracts\SocketController;
use Sockeon\Sockeon\Http\Attributes\HttpRoute;
use Sockeon\Sockeon\Http\Request;
use Sockeon\Sockeon\Http\Response;

class AdvancedApiController extends SocketController
{
    /**
     * Sample products data for demonstration
     */
    private array $products = [
        1 => ['id' => 1, 'name' => 'Laptop', 'price' => 999.99, 'category' => 'electronics'],
        2 => ['id' => 2, 'name' => 'Smartphone', 'price' => 699.99, 'category' => 'electronics'],
        3 => ['id' => 3, 'name' => 'Coffee Maker', 'price' => 89.99, 'category' => 'appliances'],
    ];
    
    /**
     * Main API index with Request method examples
     */
    #[HttpRoute('GET', '/')]
    public function index(Request $request): Response
    {
        // Demonstrate Request methods
        $clientInfo = [
            'ip' => $request->getIpAddress(),
            'url' => $request->getUrl(),
            'isAjax' => $request->isAjax() ? 'Yes' : 'No',
            'method' => $request->getMethod(),
            'userAgent' => $request->getHeader('User-Agent', 'Unknown')
        ];
        
        // Return appropriate response based on Accept header
        if (strpos($request->getHeader('Accept', ''), 'application/json') !== false) {
            return Response::json([
                'api' => 'Sockeon HTTP API',
                'client' => $clientInfo,
                'endpoints' => [
                    '/products' => 'Get all products',
                    '/products/{id}' => 'Get specific product',
                    '/filter' => 'Filter products with query parameters',
                    '/response-examples' => 'Various response examples'
                ]
            ]);
        }
        
        // Return HTML by default
        $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <title>Sockeon HTTP Examples</title>
            <style>
                body { font-family: system-ui, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
                code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; }
                .endpoint { margin-bottom: 10px; padding-left: 20px; }
                pre { background: #f8f8f8; padding: 10px; border-radius: 4px; overflow: auto; }
            </style>
        </head>
        <body>
            <h1>Sockeon HTTP Features</h1>
            
            <h2>Client Information</h2>
            <pre>IP Address: {$clientInfo['ip']}
                URL: {$clientInfo['url']}
                Method: {$clientInfo['method']}
                AJAX Request: {$clientInfo['isAjax']}
                User Agent: {$clientInfo['userAgent']}
            </pre>
                            
            <h2>Available Endpoints</h2>
            <div class="endpoint"><code>GET /products</code> - List all products</div>
            <div class="endpoint"><code>GET /products/1</code> - Get product with ID 1</div>
            <div class="endpoint"><code>GET /filter?category=electronics</code> - Filter by category</div>
            <div class="endpoint"><code>POST /products</code> - Create a new product</div>
            <div class="endpoint"><code>GET /response-examples</code> - Various response examples</div>
        </body>
        </html>
        HTML;
        
        return (new Response($html))
            ->setContentType('text/html')
            ->setHeader('X-Demo-Mode', 'enabled');
    }
    
    /**
     * Get all products - demonstrates basic Response::json
     */
    #[HttpRoute('GET', '/products')]
    public function getProducts(Request $request): Response
    {
        return Response::json([
            'products' => array_values($this->products),
            'count' => count($this->products)
        ]);
    }
    
    /**
     * Get a specific product - demonstrates path parameters and not found responses
     */
    #[HttpRoute('GET', '/products/{id}')]
    public function getProduct(Request $request): Response
    {
        $id = (int)$request->getParam('id');
        
        if (!isset($this->products[$id])) {
            return Response::notFound([
                'error' => 'Product not found',
                'id' => $id
            ]);
        }
        
        return Response::json($this->products[$id]);
    }
    
    /**
     * Filter products - demonstrates query parameters
     */
    #[HttpRoute('GET', '/filter')]
    public function filterProducts(Request $request): Response
    {
        $category = $request->getQuery('category');
        $maxPrice = $request->getQuery('maxPrice') ? (float)$request->getQuery('maxPrice') : null;
        
        $filtered = array_filter($this->products, function($product) use ($category, $maxPrice) {
            $categoryMatch = !$category || $product['category'] === $category;
            $priceMatch = !$maxPrice || $product['price'] <= $maxPrice;
            return $categoryMatch && $priceMatch;
        });
        
        return Response::json([
            'filters' => [
                'category' => $category,
                'maxPrice' => $maxPrice
            ],
            'results' => array_values($filtered),
            'count' => count($filtered)
        ]);
    }
    
    /**
     * Create product - demonstrates POST handling and status codes
     */
    #[HttpRoute('POST', '/products')]
    public function createProduct(Request $request): Response
    {
        // Check if it's a JSON request
        if (!$request->isJson()) {
            return Response::badRequest('Content-Type must be application/json');
        }
        
        $body = $request->getBody();
        
        // Validate required fields
        if (empty($body['name']) || !isset($body['price'])) {
            return Response::badRequest([
                'error' => 'Missing required fields',
                'required' => ['name', 'price']
            ]);
        }
        
        // Create new product
        $id = count($this->products) + 1;
        $product = [
            'id' => $id,
            'name' => $body['name'],
            'price' => (float)$body['price'],
            'category' => $body['category'] ?? 'uncategorized'
        ];
        
        $this->products[$id] = $product;
        
        return Response::created($product);
    }
    
    /**
     * Response examples - demonstrates various response types
     */
    #[HttpRoute('GET', '/response-examples')]
    public function responseExamples(Request $request): Response
    {
        $type = $request->getQuery('type');
        
        switch ($type) {
            case 'json':
                return Response::json(['message' => 'JSON response example']);
                
            case 'notfound':
                return Response::notFound('Resource not found');
                
            case 'error':
                return Response::serverError('Server error example');
                
            case 'unauthorized':
                return Response::unauthorized('Authentication required');
                
            case 'forbidden':
                return Response::forbidden('Access denied');
                
            case 'nocontent':
                return Response::noContent();
                
            case 'redirect':
                return Response::redirect('/');
                
            case 'download':
                $data = "ID,Name,Price\n1,Example Product,99.99";
                return Response::download($data, 'example.csv', 'text/csv');
                
            default:
                $html = <<<HTML
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Response Examples</title>
                    <style>
                        body { font-family: system-ui, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; }
                        a { display: block; margin: 5px 0; }
                    </style>
                </head>
                <body>
                    <h1>Response Type Examples</h1>
                    <p>Click the links below to see different response types:</p>
                    
                    <a href="?type=json">JSON Response</a>
                    <a href="?type=notfound">404 Not Found</a>
                    <a href="?type=error">500 Server Error</a>
                    <a href="?type=unauthorized">401 Unauthorized</a>
                    <a href="?type=forbidden">403 Forbidden</a>
                    <a href="?type=nocontent">204 No Content</a>
                    <a href="?type=redirect">302 Redirect</a>
                    <a href="?type=download">File Download</a>
                </body>
                </html>
                HTML;
                
                return (new Response($html))->setContentType('text/html');
        }
    }
}

// Initialize server instance
$server = new Server("0.0.0.0", 8000, true);

// Register our controller
$server->registerController(new AdvancedApiController());

// Start the server
echo "Starting HTTP demo server on 0.0.0.0:8000...\n";
$server->run();
