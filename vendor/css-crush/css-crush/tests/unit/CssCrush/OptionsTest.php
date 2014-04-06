<?php

namespace CssCrush\UnitTest;

use CssCrush\Options;

class OptionsTest extends \PHPUnit_Framework_TestCase
{
    public $testFile;

    public function setUp()
    {
        bootstrap_process();
        $this->testFile = temp_file("\n foo {bar: baz;} \n\n baz {bar: foo;}");
    }

    public function testDefaults()
    {
        $options = new Options();
        $initial_options = Options::$initialOptions;

        $this->assertEquals($initial_options, $options->get());

        $test_options = array('enable' => array('foo', 'bar'), 'minify' => false);
        $options = new Options($test_options);

        $initial_options_copy = $initial_options;
        $initial_options_copy = $test_options + $initial_options_copy;

        $this->assertEquals($initial_options_copy, $options->get());
    }

    public function testBoilerplate()
    {
        $boilerplate = <<<TPL
Line breaks
preserved

{{version}}
TPL;

        $result = csscrush_string('foo { bar: baz; }', array(
            'boilerplate' => temp_file($boilerplate),
            'newlines' => 'unix',
        ));

        $this->assertContains(' * ' . csscrush_version(), $result);
        $this->assertContains(" * Line breaks\n * preserved\n *", $result);
    }

    public function testFormatters()
    {
        $sample = '/* A comment */ foo {bar: baz;}';

        $single_line_expected = <<<TPL
/* A comment */
foo { bar: baz; }

TPL;
        $single_line = csscrush_string($sample, array('formatter' => 'single-line'));
        $this->assertEquals($single_line_expected, $single_line);

        $padded_expected = <<<TPL
/* A comment */
foo                                      { bar: baz; }

TPL;
        $padded = csscrush_string($sample, array('formatter' => 'padded'));
        $this->assertEquals($padded_expected, $padded);

        $block_expected = <<<TPL
/* A comment */
foo {
    bar: baz;
    }

TPL;
        $block = csscrush_string($sample, array('formatter' => 'block'));
        $this->assertEquals($block_expected, $block);
    }

    public function testSourceMaps()
    {
        csscrush_file($this->testFile, array('source_map' => true));
        $source_map_contents = file_get_contents("$this->testFile.crush.css.map");

        $this->assertRegExp('~"version": ?"3",~', $source_map_contents);
    }

    public function testTrace()
    {
        csscrush_file($this->testFile, array('trace' => true));
        $output_contents = file_get_contents("$this->testFile.crush.css");

        $this->assertContains('@media -sass-debug-info', $output_contents);

        csscrush_file($this->testFile, array('trace' => array('stubs')));
        $output_contents = file_get_contents("$this->testFile.crush.css");

        $this->assertContains('@media -sass-debug-info', $output_contents);
    }

    public function testAdvancedMinify()
    {
        $sample = "foo { color: papayawhip; color: #cccccc;}";
        $output = csscrush_string($sample, array('minify' => array('colors')));

        $this->assertEquals('foo{color:#ffefd5;color:#ccc}', $output);
    }
}
