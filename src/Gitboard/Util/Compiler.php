<?php

namespace Gitboard\Util;

/**
 * The Compiler class compiles the Gitboard utility.
 * Heavily based on Goutte compiler
 * @author André Cianfarani <acianfa@gmail.com>
 */
class Compiler
{
    public function compile($pharFile = 'gitboard.phar')
    {
        if (file_exists($pharFile)) {
            unlink($pharFile);
        }
        $shebang = "#!/usr/bin/env php\n";
        file_put_contents("gitboard.php", str_replace($shebang, "", file_get_contents("gitboard.php")));
        $phar = new \Phar($pharFile, 0, 'Gitboard');
        $phar->setSignatureAlgorithm(\Phar::SHA1);

        $phar->startBuffering();

        // CLI Component files
        foreach ($this->getFiles() as $file) {
            $path = str_replace(__DIR__.'/', '', $file);
            $phar->addFromString($path, file_get_contents($file));
        }

        // Stubs
        $phar['_cli_stub.php'] = $this->getCliStub();
        $phar['_web_stub.php'] = $this->getWebStub();
        $phar->setDefaultStub('_cli_stub.php', '_web_stub.php');

        $phar->stopBuffering();
        file_put_contents("gitboard.php", $shebang.file_get_contents("gitboard.php"));

        unset($phar);
    }

    protected function getCliStub()
    {
        return "<?php ".$this->getLicense()." require_once __DIR__.'/vendor/autoload.php'; require_once __DIR__.'/gitboard.php'; __HALT_COMPILER();";
    }

    protected function getWebStub()
    {
        return "<?php throw new \LogicException('This PHAR file can only be used from the CLI.'); __HALT_COMPILER();";
    }

    protected function getLicense()
    {
        return '
    /*
     * Copyright (c) 2013 Denis Roussel
    * Permission is hereby granted, free of charge, to any person obtaining a copy
    * of this software and associated documentation files (the "Software"), to deal
    * in the Software without restriction, including without limitation the rights
    * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    * copies of the Software, and to permit persons to whom the Software is furnished
    * to do so, subject to the following conditions:
    *
    * The above copyright notice and this permission notice shall be included in all
    * copies or substantial portions of the Software.
    *
    * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
    * THE SOFTWARE.
     */';
    }

    protected function getFiles()
    {
        $files = array(
            'gitboard.php',
            'vendor/autoload.php',
            'vendor/composer/autoload_real.php',
            'vendor/composer/autoload_namespaces.php',
            'vendor/composer/autoload_classmap.php',
            'vendor/composer/ClassLoader.php',
            'vendor/sensiolabs/ansi-to-html/SensioLabs/AnsiConverter/AnsiToHtmlConverter.php',
            'vendor/sensiolabs/ansi-to-html/SensioLabs/AnsiConverter/Theme/Theme.php'
        );

        return $files;
    }
}
