#!/usr/bin/env php
<?php

/**
 * @project less-css-compiler-js-minifier
 * @author Evgeny Chez Rumiantsev
 * @date 2014-05-26
 *
 * @use php path_to_this_script project=path_to_project_root
 */
 

require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

parse_str( implode( '&', array_slice( $argv, 1 ) ), $_GET );


$config = array();
$basedir = array();

if ( isset( $_GET['project'] ) && !empty( $_GET[ 'project' ] ) )
{
	if ( file_exists( $_GET['project'] . DIRECTORY_SEPARATOR . 'compiler.ini' ) )
	{
		$config = parse_ini_file( $_GET['project'] . DIRECTORY_SEPARATOR . 'compiler.ini', true );
	}
}

function file_search( $dir, $pattern, $wrapper_file = '_wrapper.less' )
{
	$wrapper = '';
	$content = '';

	$odir = opendir( $dir );

	$files = array();

	while ( ( $file = readdir( $odir ) ) !== FALSE )
	{
		$files[] = $file;
	}

	natsort( $files );

	foreach( $files as $file )
	{
		if ( $file == '.' || $file == '..' )
		{
			continue;
		}
		else
		{
			if ( $file === $wrapper_file )
			{
				$wrapper = file_get_contents( $dir . DIRECTORY_SEPARATOR . $file ) . "\n";
			}
			else
			{
				if ( preg_match( $pattern, $file ) )
				{
					$content .= file_get_contents( $dir . DIRECTORY_SEPARATOR . $file ) . "\n";
				}
			}
		}

		if ( is_dir( $dir . DIRECTORY_SEPARATOR . $file ) )
		{
			$content .= file_search( $dir . DIRECTORY_SEPARATOR . $file, $pattern, $wrapper_file );
		}
	}

	if ( !empty( $wrapper ) )
	{
		return str_replace( '/*@@@*/', $content, $wrapper ) . "\n";
	}
	else
	{
		return $content;
	}
}

function css_search( $dir )
{
	return file_search( $dir, '/^.*\.css$/ius', '_wrapper.css' );
}

function less_search( $dir )
{
	return file_search( $dir, '/^.*\.less$/ius', '_wrapper.less' );
}

function scss_search( $dir )
{
	return file_search( $dir, '/^.*\.scss$/ius', '_wrapper.scss' );
}

function js_search( $dir )
{
	return file_search( $dir, '/^.*\.js$/ius', '_wrapper.js' );
}

if ( empty( $basedir ) && !empty( $config ) && count( $config ) > 0 )
{
	foreach( $config as $knf => $cnf )
	{
		$basedir[0] = $_GET['project'] . DIRECTORY_SEPARATOR . $cnf['path'];

		if ( !is_dir( $basedir[0] ) )
		{
			echo "\nbad config value: [ $knf ] path={$cnf['path']}";
			continue;
		}

		$odir = opendir( $basedir[0] );

		$files = array();

		while ( ( $file = readdir( $odir ) ) !== FALSE )
		{
			if ( is_dir( $basedir[0] . $file ) )
			{
				if ( $file == '.' || $file == '..' )
				{
					continue;
				}

				if ( $cnf['compiler'] == 'css' )
				{
					if ( preg_match( '/\$([^\/]*)\.less$/', $file, $mtch ) )
					{
						$basedir[1] = $file;
						$basedir[2] = $mtch[1] . '.css';
						$output_css = '';
						$output_less = '';

						$output_css = css_search( $basedir[0] . $basedir[1], '/^.*\.css$/ius' );

						$less = new lessc();

						try
						{
							$output_less = $less->compile( less_search( $basedir[0] . $basedir[1] ) );
						}
						catch ( exception $e )
						{
							echo "\nfatal error: " . $e->getMessage(), E_USER_ERROR;
							exit ( 1 );
						}

						$output_css = $output_css . $output_less;

						if ( isset( $cnf['csso'] ) && $cnf['csso'] == 1 )
						{
							$output_css = csscrush_string( $output_css, array(
								'minify' => isset( $config['minify'] ) ? !!$cnf['minify'] : true,
								'formatter' => isset( $cnf['formatter'] ) ? $cnf['formatter'] : false,
								'boilerplate' => isset( $cnf['boilerplate'] ) ? !!$cnf['boilerplate'] : false,
								'plugins' => isset( $cnf['crush_plugins'] ) ? $cnf['crush_plugins'] : array(),
							) );
						}

						if ( $basedir[2] )
						{
							if ( file_exists( $basedir[0] . $basedir[2] ) )
							{
								unlink( $basedir[0] . $basedir[2] );
							}

							file_put_contents( $basedir[0] . $basedir[2], $output_css );
							echo "\ncompiled: {$basedir[0]}{$basedir[2]}";
						}
						else
						{
							echo "\ninvalid filename format. check $%file%.less dirname";
							exit ( 1 );
						}
					}
					elseif ( preg_match( '/\$([^\/]*)\.scss$/', $file, $mtch ) )
					{
						$basedir[1] = $file;
						$basedir[2] = $mtch[1] . '.css';
						$output_css = '';
						$output_less = '';

						$output_css = css_search( $basedir[0] . $basedir[1], '/^.*\.css$/ius' );

						$less = new scssc();

						try
						{
							$output_scss = $less->compile( scss_search( $basedir[0] . $basedir[1] ) );
						}
						catch ( exception $e )
						{
							echo "\nfatal error: " . $e->getMessage(), E_USER_ERROR;
							exit ( 1 );
						}

						$output_css = $output_css . $output_scss;

						if ( isset( $cnf['csso'] ) && $cnf['csso'] == 1 )
						{
							$output_css = csscrush_string( $output_css, array(
								'minify' => isset( $config['minify'] ) ? !!$cnf['minify'] : true,
								'formatter' => isset( $cnf['formatter'] ) ? $cnf['formatter'] : false,
								'boilerplate' => isset( $cnf['boilerplate'] ) ? !!$cnf['boilerplate'] : false,
								'plugins' => isset( $cnf['crush_plugins'] ) ? $cnf['crush_plugins'] : array(),
							) );
						}

						if ( $basedir[2] )
						{
							if ( file_exists( $basedir[0] . $basedir[2] ) )
							{
								unlink( $basedir[0] . $basedir[2] );
							}

							file_put_contents( $basedir[0] . $basedir[2], $output_css );
							echo "\ncompiled: {$basedir[0]}{$basedir[2]}";
						}
						else
						{
							echo "\ninvalid filename format. check $%file%.scss dirname";
							exit (1);
						}
					}
					else
					{
						echo "\nnot a valid dir: $file";
					}
				}
				elseif ( $cnf['compiler'] == 'js' )
				{
					if ( preg_match( '/\$([^\/]*)\.js/', $file, $mtch ) )
					{
						$basedir[1] = $file;
						$basedir[2] = $mtch[1] . '.js';
						$output_js = '';

						if ( isset( $cnf['minify'] ) && $cnf['minify'] == 'true' )
						{
							try
							{
								$output_js = \JShrink\Minifier::minify(
									js_search( $basedir[0] . $basedir[1] ),
									array(
										'flaggedComments' => false
									)
								);
							}
							catch ( \Exception $e )
							{
								echo "\nfatal error: " . $e->getMessage(), E_USER_ERROR;
								exit ( 1 );
							}
						}
						else
						{
							$output_js = js_search( $basedir[0] . $basedir[1] );
						}

						if ( $basedir[2] )
						{
							if ( file_exists( $basedir[0] . $basedir[2] ) )
							{
								unlink( $basedir[0] . $basedir[2] );
							}

							if ( !empty( $output_js ) )
							{
								file_put_contents( $basedir[0] . $basedir[2], $output_js );
								echo "\ncompiled: {$basedir[0]}{$basedir[2]}";
							}
							else
							{
								echo "\ncompile error: file string empty";
							}
						}
						else
						{
							echo "\ninvalid filename format. check $%file%.js dirname";
							exit ( 1 );
						}
					}
				}
				else
				{
					echo "\nnot a valid compiler: {$cnf['compiler']}";
				}
			}
		}
	}
}
else
{
	die(4);
}
