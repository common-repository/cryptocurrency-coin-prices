<?php

namespace cryptocurrency_prices;

if( !defined( 'ABSPATH' ) )
	exit;

require_once( 'Cmn/Gen.php' );
require_once( 'Cmn/Ui.php' );
require_once( 'Cmn/Plugin.php' );

const PLUGIN_SETT_VER								= 1;
const PLUGIN_DATA_VER								= 1;
const PLUGIN_EULA_VER								= 1;

function _LoadScripts( $workScript = true )
{
	echo( Plugin::CmnScripts( array( 'Cmn', 'Gen', 'Ui', 'Net' ) ) );

	if( !$workScript )
		return;

	wp_enqueue_script( 'cryptocurrency_prices_chart', Plugin::FileUri( 'Ext/Chart.js', __FILE__, true ), array(), '2.8.0' );
	wp_enqueue_script( 'cryptocurrency_prices_regenerator', Plugin::FileUri( 'Ext/regenerator-runtime.js', __FILE__, true ), array(), '0.13.3' );
	wp_enqueue_script( 'cryptocurrency_prices_rtn', add_query_arg( Plugin::GetFileUrlPackageParams(), Plugin::FileUri( 'rtn.js', __FILE__, true ) ), array_merge( array( 'jquery', 'cryptocurrency_prices_chart', 'cryptocurrency_prices_regenerator' ), Plugin::CmnScriptId( array( 'Cmn', 'Gen', 'Ui', 'Net' ) ) ), '1.0.1' );
}

function OnApi_GetUrl( $args )
{
	$url = @$args[ 'url' ];
	{
		switch( Net::GetSiteAddrFromUrl( $url ) )
		{
		case 'api-beta.coinexchangeprice.com':
			break;

		default:
			wp_die( null, 403 );
			return;
		}
	}

	$prms = array(   );

	$requestRes = wp_remote_get( add_query_arg( array( '_' => time() ), $url ), $prms );
	if( is_wp_error( $requestRes ) )
	{
		wp_die( '', 500 );
		return;
	}

	$httpStatus = wp_remote_retrieve_response_code( $requestRes );
	if( !( $httpStatus >= 200 && $httpStatus < 400 || $httpStatus == 403 || $httpStatus == 423 ) )
	{
	    wp_die( '', $httpStatus );
	    return;
	}

	echo( wp_remote_retrieve_body( $requestRes ) );
}

