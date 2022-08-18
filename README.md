# EXT:server_timing - see your performance

![Server-Timing](./Documentation/Server-Timing.png)

## installation

`composer require kanti/server-timing`

at the moment there is nothing to configure

> Server timings are not displayed in production for users who are not logged into the backend.

## Included measurements:

- `php`: from start of php call to the register shutdown function
- `middleware`: will show how much time was spend in the **inward** and **outward** middleware directions
- `sql`: shows the sql query's
- `extbase`: show all Extbase dispatches, (forwards are included in the original action call)
- `guzzle`: external API calls are measured if they use the official TYPO3 `RequestFactory` or the `GuzzleClientFactory`)

> if a measurement key has more than 4 entries, they will get combined into one total time with a count.
> And the 3 longest entries will be kept

## Measure your own timings:

### `stopWatch` function (recommended)

````php

  $stop = \Kanti\ServerTiming\Utility\TimingUtility::stopWatch('doSomething', 'additinal Information');
  $result = $this->doSomethingExpensive();
  $stop();

````

### `start` & `stop` functions

> this has some limitations, there can only be one `doSomething` at a time.

````php

  \Kanti\ServerTiming\Utility\TimingUtility::start('doSomething', 'additinal Information');
  $result = $this->doSomethingExpensive();
  \Kanti\ServerTiming\Utility\TimingUtility::end('doSomething');

````

# TODO List:

## todos:

- more tests
- auto release int TER

## composer patches needed?

- fluid renderings (possible solution with XClasses?)

## wanted:

- functional tests

## nice to have?

- ViewHelpers
