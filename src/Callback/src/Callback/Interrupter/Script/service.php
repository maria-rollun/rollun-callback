<?php

$path = getcwd();
if (!is_file($path . '/vendor/autoload.php')) {
    $path = dirname(getcwd());
}
chdir($path);

require 'vendor/autoload.php';

use Jaeger\Span\Context\SpanContext;
use Jaeger\Tag\StringTag;
use Jaeger\Tracer\Tracer;
use Psr\Log\LoggerInterface;
use rollun\callback\Callback\CallbackException;
use rollun\dic\InsideConstruct;
use rollun\logger\LifeCycleToken;
use rollun\logger\Processor\ExceptionBacktrace;

/** @var Laminas\ServiceManager\ServiceManager $container */
$container = include 'config/container.php';
InsideConstruct::setContainer($container);
$lifeCycleToke = LifeCycleToken::generateToken();

$callableServiceName = null;
$parentLifecycleToken = null;
$spanContext = null;

//Get argc
foreach ($argv as $i => $value) {
    if ($i === 1) {
        $callableServiceName = $value;
    } elseif (strstr($value, 'lifecycleToken') !== false) {
        [1 => $parentLifecycleToken] = explode(':', $value, 2);
    } elseif (strstr($value, 'tracerContext') !== false) {
        [1 => $tracerJsonContext] = explode(':', $value, 2);
        $spanContext = \rollun\utils\Json\Serializer::jsonUnserialize(base64_decode($tracerJsonContext));
    }
}

if ($parentLifecycleToken) {
    $lifeCycleToke->unserialize($parentLifecycleToken);
}
$container->setService(LifeCycleToken::class, $lifeCycleToke);

/** @var Tracer $tracer */
$tracer = $container->get(Tracer::class);

$logger = $container->get(LoggerInterface::class);

try {
    $span = $tracer->start('process.php', [], $spanContext);
    if ($callableServiceName === null) {
        throw new CallbackException('There is not callable service name');
    }
    $callable = $container->get($callableServiceName);

    $logger->info("Interrupter 'Process' start.", [
        'name' => $callableServiceName,
    ]);
    //$logger->debug("Serialized job: $paramsString");
    call_user_func($callable, null);
    $logger->info("Interrupter 'Process' finish.", [
        'name' => $callableServiceName,
        'memory' => memory_get_peak_usage()
    ]);
    $tracer->finish($span);
} catch (\Throwable $e) {
    $span->addTag(new StringTag('exception', json_encode((new ExceptionBacktrace())->getExceptionBacktrace($e))));
    $logger->error('When execute process, catch error', [
        'exception' => $e,
        'name' => $callableServiceName,
        'memory' => memory_get_peak_usage()
    ]);
} finally {
    $tracer->flush();
}
