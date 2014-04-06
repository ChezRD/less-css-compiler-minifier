<?php

parse_str( implode( '&', array_slice( $argv, 1 ) ), $_GET );

class SortingIterator extends ArrayIterator
{
	public function __construct( Traversable $iterator, $callback )
	{
		if ( ! is_callable( $callback ) )
		{
			throw new InvalidArgumentException( sprintf( 'Callback must be callable (%s given).', $callback ) );
		}

		parent::__construct( iterator_to_array( $iterator ) );
		$this->uasort( $callback );
	}
}

$config = array();
$basedir = array();

if ( isset( $_GET['project'] ) && !empty( $_GET[ 'project' ] ) )
{
	if ( file_exists( $_GET['project'] . '/compiler.ini' ) )
	{
		$config = parse_ini_file( $_GET['project'] . '/compiler.ini', true );
	}
}

function rsearch( $folder, $pattern )
{
	$dir = new RecursiveDirectoryIterator( $folder );
	$ite = new RecursiveIteratorIterator( $dir );
	$sorting = new SortingIterator( $ite, 'strnatcasecmp' );
	$files = new RegexIterator( $sorting, $pattern, RegexIterator::GET_MATCH );
	$fileList = array();

	foreach ( $files as $file )
	{
		$fileList = array_merge( $fileList, $file );
	}

	return $fileList;
}

function search($dir)
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
			if ( $file === '_wrapper.less' )
			{
				$wrapper = file_get_contents( $dir . DIRECTORY_SEPARATOR . $file ) . "\n";
			}
			else
			{
				if ( preg_match( '/\.less$/', $file ) )
				{
					$content .= file_get_contents( $dir . DIRECTORY_SEPARATOR . $file ) . "\n";
				}
			}
		}

		if ( is_dir( $dir . DIRECTORY_SEPARATOR . $file ) )
		{
			$content .= search( $dir . DIRECTORY_SEPARATOR . $file );
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

if ( isset( $_GET['dir'] ) && !empty( $_GET['dir'] ) )
{
	if ( is_dir( $_GET['dir'] ) )
	{
		$basedir = explode( '|', preg_replace( '/^(.+)(\$([^\/]*)\.less(?:\/|$))(.*)$/iu', '$1|$2|$3.css', $_GET['dir'] ) );

		if ( !isset( $basedir[0] ) || !isset( $basedir[1] ) || !isset( $basedir[2] ) )
		{
			echo 'there is no $%file%.less directory: ' . $_GET['dir'];
			exit (2);
		}
	}
}

if ( empty( $basedir ) && !empty( $config ) && count( $config ) > 0 )
{
	foreach( $config as $knf => $cnf )
	{
//		print_r( $cnf );
//		exit;
		$basedir[0] = $_GET['project'] . '/' . $cnf['path'];

		if ( !is_dir( $basedir[0] ) )
		{
			echo 'bad config value: path(' . $knf . ')=' . $cnf['path'] . "\n";
			continue;
		}

//		print_r( $cnf['path'] );

		$odir = opendir( $basedir[0] );

		$files = array();

//		echo $odir . '  -  ';
//		echo readdir( $odir );
//		die( $basedir[0] );

		while ( ( $file = readdir( $odir ) ) !== FALSE )
		{
			if ( is_dir( $basedir[0] . $file ) )
			{
				if ( $file == '.' || $file == '..' )
				{
					continue;
				}

				if ( preg_match( '/\$([^\/]*)\.less$/', $file, $mtch ) )
				{
					$basedir[1] = $file;
					$basedir[2] = $mtch[1] . '.css';

					$output_css = '';
					$css = rsearch( $basedir[0] . $basedir[1], '/^.*\.css$/ius' );

					foreach( $css as $item )
					{
						$output_css .= file_get_contents( $item ) . "\n";
					}

					require_once dirname( __FILE__ ) . '/vendor/autoload.php';

					$less = new lessc();

					$output_less = '';

					try
					{
						$output_less = $less->compile( search( $basedir[0] . $basedir[1] ) );
					}
					catch ( exception $e )
					{
						echo "fatal error: " . $e->getMessage(), E_USER_ERROR;
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
						unlink( $basedir[0] . $basedir[2] );
						file_put_contents( $basedir[0] . $basedir[2], $output_css );
						echo 'compiled ' . $basedir[0] . $basedir[2];
					}
					else
					{
						echo 'invalid filename format. check $%file%.less dirname';
						exit (1);
					}
				}
				else
				{
					echo 'not a valid dir: ' . $file;
				}
			}
		}

	}
}