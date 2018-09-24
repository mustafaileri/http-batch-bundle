### HttpBatchBundle
HttpBatchBundle is a plugin that allows you to packaging a set of requests by implementing `multipart/batch` method for Symfony. This bundle will allow you to decrease requests count and lower costs welcomed.

### How `multipart/batch` works?
HTTP `multipart/batch` is a format for packaging multiple HTTP requests in a single request. You can read this draft for more detail: https://tools.ietf.org/id/draft-snell-http-batch-00.html

### Installing HttpBatchBundle
The easiest way to install HttpBatchBundle is through composer.
```bash
composer require ideasoft/http-batch-bundle
```

Don't forget to register in AppKernel.php
```php
$bundles = [
            ...
            new \Ideasoft\HttpBatchBundle\HttpBatchBundle()
        ];
```
Now lets configurate it!

### Configuration
##### Routing
Add a route for HttpBatchBundle like that to your routing.yml
```yml
http_batch:
    resource: "@HttpBatchBundle/Controller/"
    type:     annotation
```

That's all. Now you can use http batch implementation on your symfony project.

Your batch request url is http://your-domain/batch. 

You should post your batch request to this url.You can change it from routing.yml if you want. You can test it with Postman or anything else.

### Do you need a multipart/batch client for PHP?
You're lucky! You can try
https://github.com/IdeasoftLabs/http-batch-client
