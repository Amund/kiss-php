<?php

namespace Kiss;

use PHPUnit\Framework\TestCase;

final class LogTest extends TestCase
{
    public function testInvoke()
    {
        $log = new class extends Log {
            public string $captured = '';
            protected function write(string $str): void
            {
                $this->captured .= $str;
            }
        };
        $log('foo');
        $this->assertSame('foo', $log->captured);
    }

    public function testInvokeWithParam()
    {
        $log = new class extends Log {
            public string $captured = '';
            protected function write(string $str): void
            {
                $this->captured .= $str;
            }
        };
        $log('foo {param}', ['{param}' => 'bar']);
        $this->assertSame('foo bar', $log->captured);
    }

    public function testLine()
    {
        $log = new class extends Log {
            public string $captured = '';
            protected function write(string $str): void
            {
                $this->captured .= $str;
            }
        };
        $log->line('foo');
        $this->assertSame("foo\n", $log->captured);
    }

    public function testLineWithParam()
    {
        $log = new class extends Log {
            public string $captured = '';
            protected function write(string $str): void
            {
                $this->captured .= $str;
            }
        };
        $log->line('foo {param}', ['{param}' => 'bar']);
        $this->assertSame("foo bar\n", $log->captured);
    }

    public function testInfo()
    {
        $log = new class extends Log {
            public string $captured = '';
            protected function write(string $str): void
            {
                $this->captured .= $str;
            }
        };
        $log->info('foo');
        $this->assertStringContainsString('foo', $log->captured);
    }

    public function testSuccess()
    {
        $log = new class extends Log {
            public string $captured = '';
            protected function write(string $str): void
            {
                $this->captured .= $str;
            }
        };
        $log->success('foo');
        $this->assertStringContainsString('foo', $log->captured);
    }

    public function testWarning()
    {
        $log = new class extends Log {
            public string $captured = '';
            protected function write(string $str): void
            {
                $this->captured .= $str;
            }
        };
        $log->warning('foo');
        $this->assertStringContainsString('foo', $log->captured);
    }

    public function testDebugWithVerboseOutputs()
    {
        $log = new class (['verbose' => true]) extends Log {
            public string $captured = '';
            protected function write(string $str): void
            {
                $this->captured .= $str;
            }
        };
        $log->debug('foo');
        $this->assertStringContainsString('foo', $log->captured);
    }

    public function testDebugWithoutVerboseSilent()
    {
        $log = new class (['verbose' => false]) extends Log {
            public string $captured = '';
            protected function write(string $str): void
            {
                $this->captured .= $str;
            }
        };
        $log->debug('foo');
        $this->assertSame('', $log->captured);
    }

    public function testErrorGoesToStderr()
    {
        $log = new class extends Log {
            public string $captured = '';
            protected function writeStderr(string $str): void
            {
                $this->captured .= $str;
            }
        };
        $log->error('foo');
        $this->assertStringContainsString('foo', $log->captured);
    }

    public function testSilentSuppressesStdout()
    {
        $log = new class extends Log {
            public string $captured = '';
            protected function write(string $str): void
            {
                $this->captured .= $str;
            }
        };
        $log->silent = true;
        $log('foo');
        $this->assertSame('', $log->captured);
    }

    public function testSilentSuppressesSuccess()
    {
        $log = new class extends Log {
            public string $captured = '';
            protected function write(string $str): void
            {
                $this->captured .= $str;
            }
        };
        $log->silent = true;
        $log->success('foo');
        $this->assertSame('', $log->captured);
    }

    public function testColor()
    {
        $result = Log::color('bold', 'foo');
        $this->assertEquals("\033[1mfoo\033[22m", $result);
    }

    public function testUnknownColor()
    {
        $result = Log::color('unknown', 'foo');
        $this->assertEquals('foo', $result);
    }

    public function testVerboseDefaultIsFalse()
    {
        $log = new Log();
        $this->assertFalse($log->verbose);
    }

    public function testVerboseFromConfig()
    {
        $log = new Log(['verbose' => true]);
        $this->assertTrue($log->verbose);
    }

    public function testMessageWithPath()
    {
        $log = new Log();
        $log->colorPath = '';
        $message = $log->message('foo', ['{path}' => 'bar']);
        $this->assertEquals('foo', $message);
    }

    public function testConstructWithNullConfig()
    {
        $log = new Log(null);
        $this->assertFalse($log->verbose);
    }

    public function testOkOutputsCheckmarkAndLabel()
    {
        $log = new class extends Log {
            public string $captured = '';
            protected function write(string $str): void
            {
                $this->captured .= $str;
            }
        };
        $log->ok('copy', 'done');
        $this->assertStringContainsString('✓', $log->captured);
        $this->assertStringContainsString('copy', $log->captured);
        $this->assertStringContainsString('done', $log->captured);
    }

    public function testOkWithDuration()
    {
        $log = new class extends Log {
            public string $captured = '';
            protected function write(string $str): void
            {
                $this->captured .= $str;
            }
        };
        $log->ok('route', 'built', '12ms');
        $this->assertStringContainsString('12ms', $log->captured);
    }

    public function testFailOutputsCrossAndLabel()
    {
        $log = new class extends Log {
            public string $captured = '';
            protected function write(string $str): void
            {
                $this->captured .= $str;
            }
        };
        $log->fail('copy', 'error');
        $this->assertStringContainsString('✗', $log->captured);
        $this->assertStringContainsString('copy', $log->captured);
        $this->assertStringContainsString('error', $log->captured);
    }

    public function testOkRespectsSilent()
    {
        $log = new class extends Log {
            public string $captured = '';
            protected function write(string $str): void
            {
                $this->captured .= $str;
            }
        };
        $log->silent = true;
        $log->ok('copy', 'done');
        $this->assertSame('', $log->captured);
    }

    public function testFailRespectsSilent()
    {
        $log = new class extends Log {
            public string $captured = '';
            protected function write(string $str): void
            {
                $this->captured .= $str;
            }
        };
        $log->silent = true;
        $log->ok('foo', 'bar');
        $log->fail('baz', 'qux');
        $this->assertSame('', $log->captured);
    }
}
