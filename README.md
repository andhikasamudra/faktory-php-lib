# Simple Faktory Client For PHP

this library i made because faktory references for php 
is obsolete this is simple working faktory lib for PHP >= 8.1

referrence from [this](https://github.com/jcs224/faktory_worker_php5) repo


### Example
```php

const $someFaktoryTopic = "topic"

$faktory = new FaktoryClient();

$job = new FaktoryJob($someFaktoryTopic, ['contents']);

$faktory->push($job);
```