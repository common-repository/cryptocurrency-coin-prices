<?php

namespace cryptocurrency_prices;

if( !defined( 'ABSPATH' ) )
	exit;

class Gen
{
	const SEVERITY_SUCCESS							= 0;
	const SEVERITY_ERROR							= 1;

	const FACILITY_INTERNET							= 12;
	const FACILITY_HTTP								= 25;

	const S_OK										= 0x00000000;
	const S_FALSE									= 0x00000001;
	const E_NOTIMPL									= 0x80004001;
	const E_INVALIDARG								= 0x80070057;
	const E_FAIL									= 0x80004005;
	const E_INVALID_STATE							= 0x8007139F;
	const E_INTERNAL								= 0x8007054F;
	const E_DATACORRUPTED							= 0x80070570;
	const E_NOT_FOUND								= 0x80070490;
	const E_ACCESS_DENIED							= 0x80070005;

	static function IsEmpty( $v )
	{
		return( empty( $v ) );
	}

	static function HrMake( $sev, $fac, $code )
	{
		return( ( $sev << 31 ) | ( $fac << 16 ) | Gen::HrCode( $code ) );
	}

	static function HrCode( $hr )
	{
		return( ( ( $hr ) & 0xFFFF ) );
	}

	static function HrFacility( $hr )
	{
		return( ( ( ( $hr ) >> 16 ) & 0x1FFF ) );
	}

	static function HrSucc( $hr )
	{
		return( !( $hr & 0x80000000 ) );
	}

	static function HrFail( $hr )
	{
		return( !self::HrSucc( $hr ) );
	}

	static function HrAccom( $hr, $hrOp )
	{
		if( $hrOp == Gen::S_FALSE )
			$hrOp = Gen::S_OK;

		if( $hr == Gen::S_FALSE )
			return( $hrOp );

		if( $hr == Gen::S_OK )
			return( Gen::HrMake( Gen::SEVERITY_SUCCESS, Gen::HrFacility( $hrOp ), $hrOp ) );

		if( Gen::HrSucc( $hr ) )
			return( $hr );

		if( Gen::HrFail( $hrOp ) )
			return( $hr );

		return( Gen::HrMake( Gen::SEVERITY_SUCCESS, Gen::HrFacility( $hr ), $hr ) );
	}

	static function GetSiteId()
	{
		$siteUrlParts = @parse_url( get_site_url() );
		if( !is_array( $siteUrlParts ) )
			return( '' );

		$res = $siteUrlParts[ 'host' ];

		$port = @$siteUrlParts[ 'port' ];
		if( $port )
			$res .= '_' . $port;

		$path = @$siteUrlParts[ 'path' ];
		if( $path )
			$res .= '_' . str_replace( array( '/', '\\' ), '_', $path );

		return( md5( $res ) );
	}

	static function GetSiteDisplayName()
	{
		$siteUrlParts = @parse_url( get_site_url() );
		if( !is_array( $siteUrlParts ) )
			return( '' );

		$res = $siteUrlParts[ 'host' ];

		$port = @$siteUrlParts[ 'port' ];
		if( $port )
			$res .= ':' . $port;

		$path = @$siteUrlParts[ 'path' ];
		if( $path )
			$res .= $path;

		return( $res );
	}

	static function GetArrField( $arr, $fieldPath, $defVal = null, $sep = '.', $bCaseIns = false )
	{
		if( !is_array( $arr ) )
			return( $defVal );

		if( !is_array( $fieldPath ) )
			$fieldPath = explode( $sep, $fieldPath );
		return( self::_GetArrField( $arr, $fieldPath, $defVal, $bCaseIns ) );
	}

	static private function _GetArrField( $v, array $fieldPath, $defVal = null, $bCaseIns = false )
	{
		if( !count( $fieldPath ) )
			return( $defVal );

		foreach( $fieldPath as $fld )
		{
			if( !is_array( $v ) )
				return( $defVal );

			$vNext = @$v[ $fld ];
			if( $vNext === null && !isset( $v[ $fld ] ) )
			{
				if( !$bCaseIns )
					return( $defVal );

				$fld = strtolower( $fld );

				$vNext = @$v[ $fld ];
				if( $vNext === null && !isset( $v[ $fld ] ) )
					return( $defVal );
			}

			$v = $vNext;
		}

		return( $v );
	}

	static function SetArrField( &$arr, $fieldPath, $val = null, $sep = '.' )
	{
		if( !is_array( $fieldPath ) )
			$fieldPath = explode( $sep, $fieldPath );
		self::_SetArrField( $arr, $fieldPath, $val );
	}

	static private function _SetArrField( &$arr, array $fieldPath, $val = null )
	{
		$n = count( $fieldPath );
		if( !$n )
			return;

		$fld = array_shift( $fieldPath );
		if( !count( $fieldPath ) )
		{
			if( $fld == '+' )
				$arr[] = $val;
			else
				$arr[ $fld ] = $val;
		}
		else
		{
			if( !is_array( $arr[ $fld ] ) )
			{
				if( $arr[ $fld ] )
					$arr[ $fld ] = array( $arr[ $fld ] );
				else
					$arr[ $fld ] = array();
			}

			self::_SetArrField( $arr[ $fld ], $fieldPath, $val );
		}
	}

	static function ToUnixSlashes( $path )
	{
		return( str_replace( '\\', '/', $path ) );
	}

	static function DoesFuncExist( $name )
	{
		$classSep = strpos( $name, '::' );
		if( $classSep === false )
			return( function_exists( $name ) );

		return( method_exists( substr( $name, 0, $classSep ), substr( $name, $classSep + 2 ) ) );
	}

	static function Serialize( $v )
	{
		return( @serialize( $v ) );
	}

	static function Unserialize( $data, $defVal = null )
	{
		if( !is_serialized( $data ) )
			return( $defVal );

		$v = @unserialize( $data );
		if( $v === false )
			return( $defVal );

		return( $v );
	}

	const CRYPT_METHOD_OPENSSL					= 'os';
	const CRYPT_METHOD_MCRYPT					= 'mc';
	const CRYPT_METHOD_XOR						= 'x';

	static function StrEncode( $str, $type = null )
	{
		if( empty( $str ) || !is_string( $str ) )
			return( '' );

		if( empty( $type ) )
		{
			if( false ) {}
			else if( function_exists( 'openssl_encrypt' ) )
				$type = self::CRYPT_METHOD_OPENSSL;
			else if( function_exists( 'mcrypt_encrypt' ) )
				$type = self::CRYPT_METHOD_MCRYPT;
			else
				$type = self::CRYPT_METHOD_XOR;
		}

		switch( $type )
		{
		case self::CRYPT_METHOD_OPENSSL:
			if( !function_exists( 'openssl_encrypt' ) )
				return( '' );

			$key = openssl_digest( wp_salt(), 'SHA256', true );

			$ivSize = ( function_exists( 'openssl_cipher_iv_length' ) && function_exists( 'openssl_random_pseudo_bytes' ) ) ? openssl_cipher_iv_length( 'AES-256-CBC' ) : null;
			$iv = null;
			if( $ivSize )
				$iv = openssl_random_pseudo_bytes( $ivSize );

			$str = openssl_encrypt( $str, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
			if( $str === false )
				return( '' );

			if( $ivSize )
				$str = $iv . $str;
			break;

		case self::CRYPT_METHOD_MCRYPT:
			if( !function_exists( 'mcrypt_encrypt' ) )
				return( '' );

			$key = md5( wp_salt(), true );
			$str = mcrypt_encrypt( MCRYPT_RIJNDAEL_256, $key, $str, MCRYPT_MODE_ECB );
			if( $str === false )
				return( '' );
			break;

		case self::CRYPT_METHOD_XOR:
			$str = self::XorData( $str, wp_salt() );
			break;

		default:
			return( '' );
			break;
		}

		$str = $type . ':' . base64_encode( $str );
		return( $str );
	}

	static function StrDecode( $str )
	{
		if( empty( $str ) || !is_string( $str ) )
			return( '' );

		$type = substr( $str, 0, 3 );
		{
			$sep = strpos( $type, ':' );
			if( $sep === false )
				$type = self::CRYPT_METHOD_MCRYPT;
			else
			{
				$type = substr( $type, 0, $sep );
				$str = substr( $str, $sep + 1 );
			}
		}

		$str = base64_decode( $str );

		switch( $type )
		{
		case self::CRYPT_METHOD_OPENSSL:
			if( !function_exists( 'openssl_decrypt' ) )
				return( '' );

			$key = openssl_digest( wp_salt(), 'SHA256', true );

			$ivSize = ( function_exists( 'openssl_cipher_iv_length' ) && function_exists( 'openssl_random_pseudo_bytes' ) ) ? openssl_cipher_iv_length( 'AES-256-CBC' ) : null;
			$iv = null;
			if( $ivSize )
			{
				$iv = substr( $str, 0, $ivSize );
				$str = substr( $str, $ivSize );
			}

			$strD = openssl_decrypt( $str, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
			if( $strD === false )
				$str = '';
			else
				$str = $strD;
			break;

		case self::CRYPT_METHOD_MCRYPT:
			if( !function_exists( 'mcrypt_decrypt' ) )
				return( '' );

			$key = md5( wp_salt(), true );
			$str = mcrypt_decrypt( MCRYPT_RIJNDAEL_256, $key, $str, MCRYPT_MODE_ECB );
			if( $str === false )
				$str = '';
			break;

		case self::CRYPT_METHOD_XOR:
			$str = self::XorData( $str, wp_salt() );
			break;

		default:
			return( '' );
			break;
		}

		return( $str );
	}

	static function XorData( $data, $key )
	{
		$n = strlen( $data );
		$nKey = strlen( $key );

		if( !$nKey )
			return( null );

		$dataNew = '';
		for( $i = 0, $iKey = 0; $i < $n; $i++, $iKey++ )
		{
			if( $iKey == $nKey )
				$iKey = 0;
			$dataNew .= $data[ $i ] ^ $key[ $iKey ];
		}

		return( $dataNew );
	}

	static function HtAccess_SetBlock( $id, $content )
	{
		$homePath = get_home_path();
		$htaccessFile = $homePath . '.htaccess';

		if( !( ( !file_exists( $htaccessFile ) && is_writable( $homePath ) ) || is_writable( $htaccessFile ) ) )
			return( Gen::S_FALSE );

		if( !got_mod_rewrite() )
			return( Gen::S_FALSE );

		return( insert_with_markers( $htaccessFile, $id, $content ) ? Gen::S_OK : Gen::S_FALSE );
	}

	static function HtAccess_QuoteUri( $uri )
	{

		$uri = str_replace( '.', '\\.', $uri );
		$uri = str_replace( '?', '\\?', $uri );
		return( $uri );
	}

	static function GetFileExt( $filepath )
	{
		$sepPos = strrpos( $filepath, '.' );
		return( $sepPos !== false ? substr( $filepath, $sepPos + 1 ) : '' );
	}

	static function GetFileName( $filepath, $nameOnly = false, $withPath = false )
	{
		if( !$withPath )
		{
			$filepath = basename( $filepath );
			if( !$nameOnly )
				return( $filepath );
		}

		$sepPos = strrpos( $filepath, '.' );
		if( $sepPos !== false )
			$filepath = substr( $filepath, 0, $sepPos );

		return( $filepath );
	}

	static function SetLastSlash( $filepath, $set = true, $slash = '/' )
	{
		$n = strlen( $filepath );
		if( !$n )
			return( '' );

		$sepPos = strrpos( $filepath, $slash );
		if( $sepPos === $n - 1 )
		{
			if( !$set )
				return( substr( $filepath, 0, $n - 1 ) );
		}
		else
		{
			if( $set )
				return( $filepath . $slash );
		}

		return( $filepath );
	}

	static function SetFirstSlash( $filepath, $set = true, $slash = '/' )
	{
		if( empty( $filepath ) )
			return( '' );

		$sepPos = strpos( $filepath, $slash );
		if( $sepPos === 0 )
		{
			if( !$set )
				return( substr( $filepath, 1 ) );
		}
		else
		{
			if( $set )
				return( $slash . $filepath );
		}

		return( $filepath );
	}

	static function ArrCopy( $arr )
	{
		$arr = array_map(
			function( $arrEl )
			{
				if( is_array( $arrEl ) )
					$arrEl = self::ArrCopy( $arrEl );
				return( $arrEl );
			},
			$arr
		);

		return( $arr );
	}

	static function ArrFlatten( $arr )
	{
		$res = array();

		foreach( $arr as $a )
		{
			if( !is_array( $a ) )
			{
				$res[] = $a;
				continue;
			}

			foreach( self::ArrFlatten( $a ) as $aSub )
				$res[] = $aSub;
		}

		return( $res );
	}

	static function ArrFromStr( $str, $sep, $cbItem = null, $cbArgs = null )
	{
		if( empty( $str ) )
			return( array() );

		$arr = explode( $sep, $str );

		if( !$cbItem )
			return( $arr );

		foreach( $arr as &$a )
			call_user_func_array( $cbItem, array( $cbArgs, &$a ) );

		return( $arr );
	}

	static function ArrGetByPos( $arr, $pos, $def = null, &$key = null )
	{
		foreach( $arr as $k => $v )
		{
			if( $pos == 0 )
			{
				$key = $k;
				return( $v );
			}
			$pos--;
		}

		return( $def );
	}

	static function StripTagsContent( $text, $tags = '', $invert = false )
	{
		if( is_string( $tags ) )
		{
			preg_match_all( '/<(.+?)[\s]*\/?[\s]*>/si', trim( $tags ), $tags );
			$tags = array_unique( $tags[ 1 ] );
		}

		if( is_array( $tags ) && count( $tags ) > 0 )
		{
			if( $invert )
				return( preg_replace( '@<(' . implode( '|', $tags ) . ')\b.*?>.*?</\1>@si', '', $text ) );
			return( preg_replace( '@<(?!(?:' . implode( '|', $tags ) . ')\b)(\w+)\b.*?>.*?</\1>@si', '', $text ) );
		}

		if( $invert )
			return( $text );

		return( preg_replace( '@<(\w+)\b.*?>.*?</\1>@si', '', $text ) );
	}

	static function GetJsHtmlContent( $text )
	{
		return( addslashes( str_replace( array( "\r", "\n" ), '', $text ) ) );
	}

	static function MinifyHtml( $html )
	{
		return( $html );

		$search = array(
			'/\>[^\S ]+/s',
			'/[^\S ]+\</s',
			'/(\s)+/s',
			'/<!--(.|\s)*?-->/'
		);

		$replace = array(
			'>',
			'<',
			'\\1',
			''
		);

		$html = preg_replace( $search, $replace, $html );
		return( $html );
	}
}

class Lang
{
	static function GetLang2LocData()
	{
		$map = array(
			'ar'			=> array( 'ar', 'ary' ),
			'az'			=> array( 'az', 'azb' ),
			'be'			=> array( 'bel' ),
			'bg'			=> array( 'bg_BG' ),
			'bn'			=> array( 'bn_BD' ),
			'bs'			=> array( 'bs_BA' ),
			'cs'			=> array( 'cs_CZ' ),
			'da'			=> array( 'da_DK' ),
			'de'			=> array( 'de_DE_formal', 'de_CH', 'de_CH_informal', 'de_DE' ),
			'dz'			=> array( 'dzo' ),
			'en'			=> array( 'en_US', 'en_ZA', 'en_CA', 'en_AU', 'en_NZ', 'en_GB' ),
			'es'			=> array( 'es_ES' ),
			'es-MX'			=> array( 'es_MX', 'es_CL', 'es_GT', 'es_VE', 'es_CR', 'es_PE', 'es_AR', 'es_CO' ),
			'fa'			=> array( 'fa_IR' ),
			'fr'			=> array( 'fr_FR', 'fr_BE', 'fr_CA' ),
			'gl'			=> array( 'gl_ES' ),
			'he'			=> array( 'he_IL' ),
			'hi'			=> array( 'hi_IN' ),
			'hu'			=> array( 'hu_HU' ),
			'id'			=> array( 'id_ID' ),
			'is'			=> array( 'is_IS' ),
			'it'			=> array( 'it_IT' ),
			'jv'			=> array( 'jv_ID' ),
			'ka'			=> array( 'ka_GE' ),
			'ko'			=> array( 'ko_KR' ),
			'ku'			=> array( 'ckb' ),
			'lt'			=> array( 'lt_LT' ),
			'mk'			=> array( 'mk_MK' ),
			'ml'			=> array( 'ml_IN' ),
			'ms'			=> array( 'ms_MY' ),
			'my'			=> array( 'my_MM' ),
			'nb'			=> array( 'nb_NO' ),
			'ne'			=> array( 'ne_NP' ),
			'nl'			=> array( 'nl_BE', 'nl_NL', 'nl_NL_formal' ),
			'nn'			=> array( 'nn_NO' ),
			'oc'			=> array( 'oci' ),
			'pa'			=> array( 'pa_IN' ),
			'pl'			=> array( 'pl_PL' ),
			'pt'			=> array( 'pt_PT', 'pt_PT_ao90' ),
			'pt-BR'			=> array( 'pt_BR' ),
			'ro'			=> array( 'ro_RO' ),
			'ru'			=> array( 'ru_RU' ),
			'si'			=> array( 'si_LK' ),
			'sk'			=> array( 'sk_SK' ),
			'sl'			=> array( 'sl_SI' ),
			'sr'			=> array( 'sr_RS' ),
			'sv'			=> array( 'sv_SE' ),
			'ta'			=> array( 'ta_IN' ),
			'tr'			=> array( 'tr_TR' ),
			'tt'			=> array( 'tt_RU' ),
			'ty'			=> array( 'tah' ),
			'ug'			=> array( 'ug_CN' ),
			'uz'			=> array( 'uz_UZ' ),
			'zh'			=> array( 'zh_CN', 'zh_HK', 'zh_TW' ),
		);

		return( $map );
	}

	static function GetLangFromLocale( $locale )
	{
		if( empty( $locale ) )
			return( null );

		$data = self::GetLang2LocData();

		foreach( $data as $dataLang => $dataLocales )
			if( array_search( $locale, $dataLocales ) !== false )
				return( $dataLang );

		return( str_replace( '_', '-', $locale ) );
	}

	static function GetLocalesFromLang( $lang )
	{
		if( empty( $lang ) )
			return( array() );

		$data = self::GetLang2LocData();

		$dataLocales = @$data[ $lang ];
		if( !empty( $dataLocales ) )
			return( $dataLocales );

		return( array( str_replace( '-', '_', $lang ) ) );
	}
}

class Net
{
	const E_TIMEOUT									= 0x800C2EE2;

	const E_HTTP_STATUS_BEGIN						= 0x100;
	const E_HTTP_STATUS_END							= 0x300;

	static function GetHrFromResponseCode( $code )
	{
		return( Gen::HrMake( $code < 400 ? Gen::SEVERITY_SUCCESS : Gen::SEVERITY_ERROR, Gen::FACILITY_HTTP, Net::E_HTTP_STATUS_BEGIN + $code ) );
	}

	static function GetHrFromWpRemoteGet( $requestRes )
	{
		if( !$requestRes )
			return( Gen::E_FAIL );

		if( !is_wp_error( $requestRes ) )
		{
			$httpStatus = wp_remote_retrieve_response_code( $requestRes );
			if( $httpStatus == 200 )
				return( Gen::S_OK );
			return( Net::GetHrFromResponseCode( $httpStatus ) );
		}

		$errCode = $requestRes -> get_error_code();
		$errMsg = $requestRes -> get_error_message( $errCode );

		if( $errCode == 'http_request_failed' && strpos( $errMsg, 'cURL error 28:' ) !== false )
			return( Net::E_TIMEOUT );

		return( Gen::E_FAIL );
	}

	static function GetSiteAddrFromUrl( $url, $withScheme = false )
	{
		$siteUrlParts = @parse_url( $url );
		if( !is_array( $siteUrlParts ) )
			return( null );

		$port = @$siteUrlParts[ 'port' ];
		return( ( $withScheme ? ( $siteUrlParts[ 'scheme' ] . '://' ) : '' ) . $siteUrlParts[ 'host' ] . ( empty( $port ) ? '' : ( ':' . $port ) ) );
	}

	static function GetUrlWithoutProto( $url )
	{
		$pos = strpos( $url, '://' );
		if( $pos === false )
			return( $url );
		return( substr( $url, $pos + 3 ) );
	}

	static function Url2Uri( $url, $siteUrlRelative = false )
	{
		if( !$siteUrlRelative )
		{
			$url = self::GetUrlWithoutProto( $url );

			$pos = strpos( $url, '/' );
			if( $pos === false )
				return( '' );

			return( substr( $url, $pos ) );
		}

		$siteUrl = self::GetUrlWithoutProto( Gen::SetLastSlash( get_site_url(), false ) );
		$url = self::GetUrlWithoutProto( $url );

		if( strpos( $url, $siteUrl ) !== 0 )
			return( $url );
		return( substr( $url, strlen( $siteUrl ) ) );
	}
}

class HtmlNd
{
	static function Parse( $str, $options = null, $encoding = 'UTF-8' )
	{
		if( $options === null )
			$options = LIBXML_NONET|LIBXML_NOBLANKS;

		if( empty( $str ) )
			return( null );

		if( $options & LIBXML_NOBLANKS )
		{
			$str = str_replace( "\r", '', $str );
			$str = str_replace( "\t", '', $str );
		}

		$doc = @\DOMDocument::loadHTML( '<!DOCTYPE html><html><head><meta charset="' . $encoding . '"></head><body>' . $str . '</body></html>', $options|LIBXML_HTML_NOIMPLIED );
		if( !$doc )
			return( null );

		$nd = self::FindByTag( $doc, 'body' );

		if( $options & LIBXML_NOBLANKS )
			self::_CleanEmptyChildren( $nd );

		return( $nd );
	}

	private static function _CleanEmptyChildren( $nd )
	{
		$children = $nd -> childNodes;
		if( !$children )
			return;

		for( $i = 0; $i < $children -> length; $i++ )
		{
			$child = $children -> item( $i );
			if( $child -> nodeType == XML_TEXT_NODE && Gen::IsEmpty( trim( $child -> textContent ) ) )
			{
				$nd -> removeChild( $child );
				$i --;
			}
			else
				self::_CleanEmptyChildren( $child );
		}
	}

	static function DeParse( $nd, $includeSelf = true )
	{
		if( !$nd || !$nd -> ownerDocument )
			return( null );

		if( $nd -> nodeName == 'body' )
			$includeSelf = false;

		if( $includeSelf )
			return( $nd -> ownerDocument -> saveHTML( $nd ) );

		$children = $nd -> childNodes;
		if( !$children )
			return( '' );

		$res = '';
		for( $i = 0; $i < $children -> length; $i++ )
		{
			$child = $children -> item( $i );
			$res .= $nd -> ownerDocument -> saveHTML( $child );
		}

		return( $res );
	}

	static function FindByTag( $nd, $tag )
	{
		if( !$nd )
			return( null );

		if( $nd -> nodeName == $tag )
			return( $nd );

		$children = $nd -> childNodes;
		if( !$children )
			return( null );

		for( $i = 0; $i < $children -> length; $i++ )
		{
			$ndRes = self::FindByTag( $children -> item( $i ), $tag );
			if( $ndRes )
				return( $ndRes );
		}

		return( null );
	}

	static function GetChildrenCount( $nd )
	{
		if( !$nd )
			return( 0 );

		$children = $nd -> childNodes;
		if( !$children )
			return( 0 );

		return( $children -> length );
	}

	static function GetChild( $nd, $i )
	{
		if( !$nd )
			return( null );

		$children = $nd -> childNodes;
		if( !$children )
			return( null );

		if( $i >= $children -> length )
			return( null );

		return( $children -> item( $i ) );
	}

	static function RemoveChild( $nd, $i )
	{
		if( !$nd )
			return( null );

		$children = $nd -> childNodes;
		if( !$children )
			return( null );

		if( $i >= $children -> length )
			return( null );

		$child = $children -> item( $i );
		$nd -> removeChild( $child );
		return( $child );
	}

	static function GetAttrVal( $nd, $name )
	{
		if( !$nd || !$nd -> attributes )
			return( null );

		$attr = $nd -> attributes -> getNamedItem( $name );
		if( !$attr )
			return( null );

		return( $attr -> nodeValue );
	}

	static function SetAttrVal( $nd, $name, $val )
	{
		if( !$nd )
			return( false );

		$nd -> setAttribute( $name, $val );
		return( true );
	}
}

if( defined( 'T_ELEMENT' ) )
	exit( -1 );

const T_ELEMENT			= 10001;

class Php
{
	const TI_ID					= 0;
	const TI_CONTENT			= 1;
	const TI_LINENUM			= 2;

	const T_OPEN_TAG_CONTENT	= '<?php';

	static function Token_GetIdName( $id )
	{
		if( $id == T_ELEMENT )
			return( 'T_ELEMENT' );
		return( token_name( $id ) );
	}

	static function Token_GetContent( $token, $id = null )
	{
		if( !$token )
			return( null );

		if( $id !== null && $token[ Php::TI_ID ] != $id )
			return( null );

		return( $token[ Php::TI_CONTENT ] );
	}

	static function Token_GetEncapsedStrVal( $str )
	{
		return( substr( $str, 1, -1 ) );
	}

	static function Token_IdMatch( $token, $id )
	{
		if( is_array( $id ) )
		{
			foreach( $id as $idItem )
				if( self::Token_IdMatch( $token, $idItem ) )
					return( true );

			return( false );
		}

		if( is_string( $id ) )
		{
			if( is_array( $token ) )
				return( false );

			if( $id != $token )
				return( false );

			return( true );
		}

		if( !is_array( $token ) )
			return( false );

		if( $token[ Php::TI_ID ] != $id )
			return( false );

		return( true );
	}

	static function Tokens_GetSpaceIds()
	{
		$ids = Php::Tokens_GetCommentIds();
		$ids[] = T_WHITESPACE;

		return( $ids );
	}

	static function Tokens_GetCommentIds()
	{
		$ids = array( T_COMMENT );
		if( defined( 'T_ML_COMMENT' ) )
			$ids[] = T_ML_COMMENT;
		if( defined( 'T_DOC_COMMENT' ) )
			$ids[] = T_DOC_COMMENT;

		return( $ids );
	}

	static function Tokens_Normalize( &$tokens, $preserveLineNums = false )
	{
		$tokenLineNum = 0;
		for( $i = 0; $i < count( $tokens ); $i++ )
		{
			$token = &$tokens[ $i ];

			if( !is_array( $token ) )
			{
				$tokenNew = array( Php::TI_ID => T_ELEMENT, Php::TI_CONTENT => $token );
				if( $preserveLineNums )
					$tokenNew[ Php::TI_LINENUM ] = $tokenLineNum;

				$tokens[ $i ] = $tokenNew;
			}
			else
			{
				$tokenLineNum = $token[ Php::TI_LINENUM ];
				if( !$preserveLineNums )
					unset( $token[ Php::TI_LINENUM ] );
			}

			if( $token[ Php::TI_ID ] == T_INLINE_HTML )
				continue;

			if( $token[ Php::TI_ID ] == T_WHITESPACE )
				continue;

			$tokenVal_Ls = '';
			$tokenVal = null;
			$tokenVal_Rs = '';
			{
				$tokenValT = ltrim( $token[ Php::TI_CONTENT ] );
				$tokenVal_Ls = substr( $token[ Php::TI_CONTENT ], 0, strlen( $token[ Php::TI_CONTENT ] ) - strlen( $tokenValT ) );
				$tokenVal = rtrim( $tokenValT );
				$tokenVal_Rs = substr( $tokenValT, strlen( $tokenVal ), strlen( $tokenValT ) );
			}

			$token[ Php::TI_CONTENT ] = $tokenVal;

			if( $tokenVal_Ls )
			{
				$tokenNew = array( Php::TI_ID => T_WHITESPACE, Php::TI_CONTENT => $tokenVal_Ls );
				if( $preserveLineNums )
					$tokenNew[ Php::TI_LINENUM ] = $tokenLineNum;

				Php::Tokens_Insert( $tokens, $i, array( $tokenNew ) );
				$i++;
			}

			if( $tokenVal_Rs )
			{
				$tokenNew = array( Php::TI_ID => T_WHITESPACE, Php::TI_CONTENT => $tokenVal_Rs );
				if( $preserveLineNums )
					$tokenNew[ Php::TI_LINENUM ] = $tokenLineNum;

				Php::Tokens_Insert( $tokens, $i + 1, array( $tokenNew ) );
				$i++;
			}
		}
	}

	static function Tokens_Find( &$tokens, $id, $content = null, $pos = 0, $length = 0 )
	{
		$n = count( $tokens );
		if( $length > 0 )
		{
			$nNew = $pos + $length;
			if( $nNew <= $n )
				$n = $nNew;
		}

		if( !is_array( $id ) )
			$id = array( 'i' => array( $id ) );

		if( $content !== null && !is_array( $content ) )
			$content = array( $content );

		for( ; $pos < $n; $pos++ )
		{
			$token = $tokens[ $pos ];

			{
				$match = true;
				$idsList = @$id[ 'e' ];
				if( !$idsList )
				{
					$idsList = @$id[ 'i' ];
					$match = false;
				}

				if( is_array( $idsList ) && Php::Token_IdMatch( $token, $idsList ) )
					$match = !$match;

				if( !$match )
					continue;
			}

			if( $content !== null )
			{
				$contentFound = false;
				foreach( $content as $contentItem )
				{
					if( $token[ Php::TI_CONTENT ] == $contentItem )
					{
						$contentFound = true;
						break;
					}
				}

				if( !$contentFound )
					continue;
			}

			return( $pos );
		}

		return( false );
	}

	static function Tokens_Insert( &$tokens, $pos, $a )
	{
		$n = count( $tokens );
		array_splice( $tokens, $pos > $n ? $n : $pos, 0, $a );
	}

	static function Tokens_GetFromContent( $str, $preserveLineNums = false )
	{
		$tokens = @token_get_all( $str );
		Php::Tokens_Normalize( $tokens, $preserveLineNums );
		return( $tokens );
	}

	static function Tokens_GetContent( $tokens )
	{
		$res = '';

		for( $i = 0, $n = count( $tokens ); $i < $n; $i++ )
		{
			$token = $tokens[ $i ];
			$res .= is_array( $token ) ? $token[ Php::TI_CONTENT ] : $token;
		}

		return( $res );
	}

	static function Tokens_CallArgs_GetSingleArg( $callArgs, $idx, &$argTokenPos = null )
	{
		$arg = @$callArgs[ $idx ];
		if( $arg === null )
			return( null );

		return( count( $arg ) == 1 ? Gen::ArrGetByPos( $arg, 0, null, $argTokenPos ) : null );
	}

	static function Tokens_GetCallArgs( $tokens, &$pos, $preserveSpaces = false )
	{
		$spacesIds = Php::Tokens_GetSpaceIds();

		$pos = Php::Tokens_Find( $tokens, array( 'e' => $spacesIds ), null, $pos );
		if( $pos === false )
			return( false );

		if( $tokens[ $pos ] != array( Php::TI_ID => T_ELEMENT, Php::TI_CONTENT => '(' ) )
			return( false );

		$pos++;

		$res = array();

		$bracketsLevel = 1;
		$argIdx = 0;

		for( $n = count( $tokens ); $pos < $n; $pos++ )
		{
			$token = $tokens[ $pos ];

			if( $token == array( Php::TI_ID => T_ELEMENT, Php::TI_CONTENT => ')' ) )
			{
				$bracketsLevel--;
				if( $bracketsLevel == 0 )
					break;
			}

			if( $bracketsLevel == 1 && $token == array( Php::TI_ID => T_ELEMENT, Php::TI_CONTENT => ',' ) )
			{
				$argIdx++;
				continue;
			}

			if( $token == array( Php::TI_ID => T_ELEMENT, Php::TI_CONTENT => '(' ) )
				$bracketsLevel++;

			if( !$preserveSpaces && self::Token_IdMatch( $token, $spacesIds ) )
				continue;

			$res[ $argIdx ][ $pos ] = $token;
		}

		return( $res );
	}

	static function File_SetDefineVal( $file, $name, $val )
	{
		if( !file_exists( $file ) )
			return( Gen::E_NOT_FOUND );

		if( !is_writable( $file ) )
			return( Gen::E_ACCESS_DENIED );

		$fileContent = file_get_contents( $file );
		if( !$fileContent )
			return( Gen::E_ACCESS_DENIED );

		if( !Php::Content_SetDefineVal( $fileContent, $name, $val ) )
			return( Gen::S_FALSE );

		if( !is_integer( file_put_contents( $file, $fileContent, LOCK_EX ) ) )
			return( Gen::E_FAIL );

		return( Gen::S_OK );
	}

	static function Content_SetDefineVal( &$fileContent, $name, $val )
	{
		$tokens = Php::Tokens_GetFromContent( $fileContent );
		if( !Php::Tokens_SetDefineVal( $tokens, $name, $val ) )
			return( false );

		$fileContent = Php::Tokens_GetContent( $tokens );
		return( true );
	}

	static function Tokens_SetDefineVal( &$tokens, $name, $val )
	{

		$firstInsertPos = Php::Tokens_Find( $tokens, T_OPEN_TAG );
		if( $firstInsertPos === false )
		{
			$tokensInsert = array();
			$tokensInsert[] = array( Php::TI_ID => T_OPEN_TAG, Php::TI_CONTENT => Php::T_OPEN_TAG_CONTENT );
			$tokensInsert[] = array( Php::TI_ID => T_WHITESPACE, Php::TI_CONTENT => PHP_EOL . PHP_EOL );

			Php::Tokens_Insert( $tokens, count( $tokens ), $tokensInsert );

			$firstInsertPos = count( $tokens );
		}
		else
		{
			$firstInsertPos++;

			$firstInsertPos = Php::Tokens_Find( $tokens, array( 'e' => Php::Tokens_GetSpaceIds() ), null, $firstInsertPos );
			if( $firstInsertPos === false )
				$firstInsertPos = count( $tokens );
		}

		$defineValPos = false;
		for( $i = $firstInsertPos; ; )
		{
			$i = Php::Tokens_Find( $tokens, T_STRING, 'define', $i );
			if( $i === false )
				break;
			$i++;

			$callArgs = Php::Tokens_GetCallArgs( $tokens, $i );
			if( empty( $callArgs ) || count( $callArgs ) != 2 )
				continue;

			if( Php::Token_GetEncapsedStrVal( Php::Token_GetContent( Php::Tokens_CallArgs_GetSingleArg( $callArgs, 0 ), T_CONSTANT_ENCAPSED_STRING ) ) != $name )
				continue;

			Php::Tokens_CallArgs_GetSingleArg( $callArgs, 1, $defineValPos );
			break;
		}

		$changed = false;

		if( $defineValPos === false )
		{
			$tokensInsert = array();
			$tokensInsert[] = array( Php::TI_ID => T_STRING, Php::TI_CONTENT => 'define' );
			$tokensInsert[] = array( Php::TI_ID => T_ELEMENT, Php::TI_CONTENT => '(' );
			$tokensInsert[] = array( Php::TI_ID => T_CONSTANT_ENCAPSED_STRING, Php::TI_CONTENT => '\'' . $name . '\'' );
			$tokensInsert[] = array( Php::TI_ID => T_ELEMENT, Php::TI_CONTENT => ',' );
			$tokensInsert[] = array( Php::TI_ID => T_WHITESPACE, Php::TI_CONTENT => ' ' );

			{
				$defineValPos = count( $tokensInsert );
				$tokensInsert[] = array( Php::TI_ID => T_CONSTANT_ENCAPSED_STRING, Php::TI_CONTENT => '\'\'' );
			}

			$tokensInsert[] = array( Php::TI_ID => T_ELEMENT, Php::TI_CONTENT => ')' );
			$tokensInsert[] = array( Php::TI_ID => T_ELEMENT, Php::TI_CONTENT => ';' );
			$tokensInsert[] = array( Php::TI_ID => T_WHITESPACE, Php::TI_CONTENT => PHP_EOL . PHP_EOL );

			Php::Tokens_Insert( $tokens, $firstInsertPos, $tokensInsert );
			$defineValPos += $firstInsertPos;

			$changed = true;
		}

		{

			$tokenValNew = null;
			switch( gettype( $val ) )
			{
			case 'string':
				$token = $tokens[ $defineValPos ];

				$cQuote = null;
				if( $token[ Php::TI_ID ] == T_CONSTANT_ENCAPSED_STRING )
					$cQuote = substr( $token[ Php::TI_CONTENT ], 0, 1 );

				if( empty( $cQuote ) )
					$cQuote = '\'';

				$tokenValNew = array( Php::TI_ID => T_CONSTANT_ENCAPSED_STRING, Php::TI_CONTENT => $cQuote . $val . $cQuote );
				break;

			case 'boolean':
				$tokenValNew = array( Php::TI_ID => T_STRING, Php::TI_CONTENT => $val ? 'true' : 'false' );
				break;

			case 'integer':
				$tokenValNew = array( Php::TI_ID => T_LNUMBER, Php::TI_CONTENT => '' . $val );
				break;

			case 'double':
			    $tokenValNew = array( Php::TI_ID => T_DNUMBER, Php::TI_CONTENT => '' . $val );
			    break;

			default:
				return( false );
				break;
			}

			if( $tokens[ $defineValPos ] != $tokenValNew )
			{
				$tokens[ $defineValPos ] = $tokenValNew;
				$changed = true;
			}
		}

		return( true );
	}
}

class WpFakePostContainer
{
	public function __construct( $post )
	{
		$this -> post = $post;
		wp_cache_add( $this -> post -> ID, $post, 'posts' );
	}

	function __destruct()
	{
		wp_cache_delete( $this -> post -> ID, 'posts' );
	}

	public $post;
}

class Wp
{
	static function GetTempFile()
	{
		$fileNamePathTmp = tempnam( sys_get_temp_dir(), substr( '', 0, 3 ) );
		if( !empty( $fileNamePathTmp ) )
			return( $fileNamePathTmp );

		return( wp_tempnam() );
	}

	static function GetLocString( $id )
	{
		return( __( $id ) );
	}

	static function GetPostIdByPath( $path, $post_type = 'post', $lang = null )
	{
		$post = get_page_by_path( $path, OBJECT, $post_type );
		if( !$post )
			return( null );

		$id = apply_filters( 'translate_object_id', $post -> ID, $post -> post_type, true, $lang );
		return( $id );
	}

	static function CreateFakePostContainer( $postType = 'post' )
	{
		$post = new \WP_Post( ( object )array( 'ID' => -1 ) );
		$post -> post_type = $postType;
		$post -> post_status = 'auto-draft';
		$post -> post_title = '';

		return( new WpFakePostContainer( $post ) );
	}

	const POST_TAXONOMY_TYPE_CATEGORY						= 'category';
	const POST_TAXONOMY_TYPE_TAG							= 'tag';

	static private function _GetTaxonomyMetaId( $type )
	{
		$map = array(
			self::POST_TAXONOMY_TYPE_CATEGORY				=> 'categories',
			self::POST_TAXONOMY_TYPE_TAG					=> 'tags',
		);

		return( $map[ $type ] );
	}

	static function GetPostsTaxonomies( $type )
	{
		$taxonomies = get_taxonomies( NULL, 'objects' );
		if( !is_array( $taxonomies ) )
			return( array() );

		$res = array();

		$taxonomyMetaCbName = 'post_' . self::_GetTaxonomyMetaId( $type ) . '_meta_box';

		foreach( $taxonomies as $taxonomyId => $taxonomy )
		{
			if( !$taxonomy -> show_ui )
				continue;

			if( $taxonomy -> meta_box_cb != $taxonomyMetaCbName )
				continue;

			foreach( $taxonomy -> object_type as $taxonomyPostType )
				$res[ $taxonomyPostType ] = $taxonomyId;
		}

		return( $res );
	}

	static function GetPostsAvailableTaxonomies( $type, $postTypes = NULL )
	{
		$mapPostTypeToTaxonomy = Wp::GetPostsTaxonomies( $type );

		$cats = array();

		if( empty( $postTypes ) )
			$postTypes = get_post_types();

		$filters = Wp::RemoveLangFilters();

		foreach( $postTypes as $postType )
		{
			$postTaxonomy = @$mapPostTypeToTaxonomy[ $postType ];
			if( empty( $postTaxonomy ) )
				continue;

			$postCats = get_terms( $postTaxonomy, array( 'hide_empty' => false ) );
			if( empty( $postCats ) )
				continue;

			$postCatsNew = array();
			foreach( $postCats as $postCat )
			{
				$postCatNew = array( 'slug' => $postCat -> slug, 'name' => $postCat -> name, 'parent' => $postCat -> parent );
				$postCatsNew[ $postCat -> term_id ] = $postCatNew;
			}

			$cats[ $postType ] = $postCatsNew;
		}

		Wp::AddFilters( $filters );

		if( Wp::IsLangsActive() )
			foreach( $cats as &$type )
			{
				$postTaxonomy = @$mapPostTypeToTaxonomy[ $postType ];
				foreach( $type as $id => &$info )
					$info[ 'lang' ] = Wp::GetElemLang( $id, $postTaxonomy );
			}

		return( $cats );
	}

	static function UpdatePostTypeTaxonomies( $terms, $type, $postType, $lang = null )
	{
		$mapPostTypeToTaxonomy = Wp::GetPostsTaxonomies( $type );
		$postTaxonomy = @$mapPostTypeToTaxonomy[ $postType ];
		if( !$postTaxonomy )
			return( null );

		$res = array();

		$filters = Wp::RemoveLangFilters();

		$langDef = Wp::GetDefLang();

		foreach( $terms as $term )
		{
			$term = trim( $term );
			if( !strlen( $term ) )
			{
				$res[] = 0;
				continue;
			}

			$termId = 0;
			{
				$termInfo = wp_insert_term( $term, $postTaxonomy );
				if( is_wp_error( $termInfo ) )
				{
					$termIdExisted = intval( $termInfo -> get_error_data( 'term_exists' ) );
					if( $termIdExisted > 0 )
					{
						if( $lang )
						{
							$termExistedLang = Wp::GetElemLang( $termIdExisted, $postTaxonomy );
							if( $termExistedLang != $lang )
							{
								if( $langDef != $termExistedLang )
								{
									$termInfo = get_term( $termIdExisted, $postTaxonomy );
									if( !is_wp_error( $termInfo ) )
										wp_update_term( $termIdExisted, $postTaxonomy, array( 'slug' => $termInfo -> slug . ' ' . $termExistedLang ) );
								}

								$slug = $term . ( $langDef != $lang ? ( ' ' . $lang ) : '' );
								$termInfo = get_term_by( 'slug', $slug, $postTaxonomy );
								if( $termInfo )
									$termId = $termInfo -> term_id;
								else
								{
									$termInfo = wp_insert_term( $term, $postTaxonomy, array( 'slug' => $slug ) );
									if( !is_wp_error( $termInfo ) )
										$termId = intval( $termInfo[ 'term_id' ] );
								}
							}
							else
								$termId = $termIdExisted;
						}
						else
							$termId = $termIdExisted;
					}
				}
				else
					$termId = intval( $termInfo[ 'term_id' ] );
			}

			$res[] = $termId;

			if( $lang && $termId )
				Wp::SetElemLang( $termId, $lang, $postTaxonomy );
		}

		Wp::AddFilters( $filters );

		return( $res );
	}

	static function FindTermByField( array $terms, string $field, $fieldValue, $fieldRet = '', $fieldRetDef = null )
	{
		foreach( $terms as $term )
			if( $term -> $field == $fieldValue )
				return( empty( $fieldRet ) ? $term : $term -> $fieldRet );

		return( empty( $fieldRet ) ? null : $fieldRetDef );
	}

	static function GetSupportsPostsTypes( $supportsList )
	{
		$res = array();

		$postTypes = get_post_types();
		foreach( $postTypes as $postType )
		{
			$supportsResCount = 0;
			foreach( $supportsList as $supportsItem )
				if( post_type_supports( $postType, $supportsItem ) )
					$supportsResCount++;

			if( $supportsResCount < count( $supportsList ) )
				continue;

			$res[] = $postType;
		}

		return( $res );
	}

	static function GetAvailableThumbnails()
	{
		global $_wp_additional_image_sizes;

		$thumbnail_sizes = array();

		$sizeNames = apply_filters( 'image_size_names_choose', array(
			'thumbnail' => Wp::GetLocString( 'Thumbnail' ),
			'medium'    => Wp::GetLocString( 'Medium' ),
			'large'     => Wp::GetLocString( 'Large' ),
			'full'      => Wp::GetLocString( 'Full Size' ),
		) );

		foreach( get_intermediate_image_sizes() as $id )
		{
			$name = @$sizeNames[ $id ];
			if( empty( $name ) )
			{
				$name = $id;
				$name = str_replace( array( '-', '_' ), ' ', $name );
				$name = strtoupper( substr( $name, 0, 1 ) ) . substr( $name, 1 );
			}

			$thumbnail_sizes[ $id ][ 'name' ] = $name;

			if( in_array( $id, array( 'thumbnail', 'medium', 'medium_large', 'large' ) ) )
			{
				$thumbnail_sizes[ $id ][ 'width' ]  = ( int )get_option( $id . '_size_w' );
				$thumbnail_sizes[ $id ][ 'height' ] = ( int )get_option( $id . '_size_h' );
				$thumbnail_sizes[ $id ][ 'crop' ]   = ( 'thumbnail' == $id ) ? ( bool )get_option( 'thumbnail_crop' ) : false;
			}
			else if( !empty( $_wp_additional_image_sizes ) && !empty( $_wp_additional_image_sizes[ $id ] ) )
			{
				$thumbnail_sizes[ $id ][ 'width' ]  = ( int )$_wp_additional_image_sizes[ $id ][ 'width' ];
				$thumbnail_sizes[ $id ][ 'height' ] = ( int )$_wp_additional_image_sizes[ $id ][ 'height' ];
				$thumbnail_sizes[ $id ][ 'crop' ]   = ( bool )$_wp_additional_image_sizes[ $id ][ 'crop' ];
			}
		}

		return( $thumbnail_sizes );
	}

	static function GetMediaUploadUrl( $postFrom, $siteUrlRelative = false )
	{
		if( is_int( $postFrom ) )
			$postFrom = get_post( $postFrom );

		$post_img_dir = null;
		if( $postFrom )
		{
			global $post, $post_id;

			$post_prev = $post;
			$post = null;
			$post_id_prev = $post_id;
			$post_id = $postFrom -> ID;

			$file = apply_filters( 'wp_handle_upload_prefilter', array( 'name' => 'dummy.jpg', 'ext'  => 'jpg', 'type' => 'jpg' ) );

			$wp_upload_dir_res = wp_upload_dir( null, false );
			$post_img_dir = $wp_upload_dir_res[ 'url' ];

			$fileinfo = apply_filters( 'wp_handle_upload', array( 'file' => $file[ 'name' ], 'url'  => $post_img_dir . '/' . $file[ 'name' ], 'type' => $file[ 'type' ] ), 'upload' );

			$post = $post_prev;
			$post_id = $post_id_prev;
		}
		else
		{
			$wp_upload_dir_res = wp_upload_dir( null, false );
			$post_img_dir = $wp_upload_dir_res[ 'baseurl' ];
		}

		return( Net::Url2Uri( $post_img_dir, $siteUrlRelative ) );
	}

	static function GetAttachmentIdFromUrl( $attachment_url = '', $lang = null )
	{
		global $wpdb;

		if( empty( $attachment_url ) )
			return( null );

		{
			$siteAddr = Net::GetSiteAddrFromUrl( $attachment_url );
			if( !empty( $siteAddr ) && $siteAddr != Net::GetSiteAddrFromUrl( get_site_url() ) )
				return( null );
		}

		$attachment_url = Net::Url2Uri( $attachment_url );

		$upload_dir_path = wp_upload_dir();
		$upload_dir_path = @$upload_dir_path[ 'baseurl' ];
		if( !$upload_dir_path )
			return( null );

		{
			$checkUris = array(
				Net::Url2Uri( $upload_dir_path, false ),
				Net::Url2Uri( $upload_dir_path, true )
			);

			$checkedIdx = 0;
			foreach( $checkUris as $checkUri )
			{
				if( strpos( $attachment_url, $checkUri ) === 0 )
					break;

				$checkedIdx ++;
			}

			if( $checkedIdx == count( $checkUris ) )
				return( null );

			$upload_dir_path = $checkUris[ $checkedIdx ];
		}

		$attachment_id = null;

		$attachment_url = preg_replace( '/-\d+x\d+(?=\.(jpg|jpeg|png|gif)$)/i', '', $attachment_url );

		$attachment_url = str_replace( $upload_dir_path . '/', '', $attachment_url );

		$attachments = $wpdb -> get_results( $wpdb -> prepare( "SELECT wposts.ID FROM $wpdb->posts wposts, $wpdb->postmeta wpostmeta WHERE wposts.ID = wpostmeta.post_id AND wpostmeta.meta_key = '_wp_attached_file' AND wpostmeta.meta_value = '%s' AND wposts.post_type = 'attachment'", $attachment_url ), ARRAY_A );
		foreach( $attachments as $attachment )
		{
			$aId = $attachment[ 'ID' ];

			$aLang = Wp::GetPostLang( $aId );
			if( $aLang == $lang )
			{
				$attachment_id = $aId;
				break;
			}
		}

		return( $attachment_id );
	}

	static function UpdateAttachment( $attachId, $data, $wp_error = false )
	{
		$data[ 'ID' ] = $attachId;

		$image_alt = @$data[ '_wp_attachment_image_alt' ];
		if( $image_alt )
			update_post_meta( $attachId, '_wp_attachment_image_alt', $image_alt );

		return( wp_update_post( $data, $wp_error ) );
	}

	static function GetAttachment( $attachId, $output = OBJECT )
	{
		$data = get_post( $attachId, ARRAY_A );
		if( !$data )
			return( null );

		$image_alt = get_post_meta( $attachId, '_wp_attachment_image_alt', true );
		if( $image_alt )
			$data[ '_wp_attachment_image_alt' ] = $image_alt;

		if( $output == OBJECT )
			$data = ( object )$data;
		return( $data );
	}

	const REMOVEFILTER_PRIORITY_ALL							= null;
	const REMOVEFILTER_FUNCNAME_ALL							= null;
	const REMOVEFILTER_TAG_ALL								= null;

	static private function _RemoveFilter_IsEqual( $fn, $fnRem )
	{
		if( is_string( $fn ) )
		{
			$sepClass = strpos( $fn, '::' );
			if( $sepClass !== false )
				$fn = array( substr( $fn, 0, $sepClass ), substr( $fn, $sepClass + strlen( '::' ) ) );
		}

		if( $fn == $fnRem )
			return( true );

		if( is_array( $fn ) && count( $fn ) == 2 )
		{
			$fnObj = $fn[ 0 ];
			$fnName = $fn[ 1 ];

			if( is_array( $fnRem ) && count( $fnRem ) == 2 )
			{
				$fnRemClass = $fnRem[ 0 ];
				$fnRemName = $fnRem[ 1 ];

				if( $fnRemName === Wp::REMOVEFILTER_FUNCNAME_ALL || $fnRemName == $fnName )
					if( is_string( $fnRemClass ) && is_object( $fnObj ) && $fnRemClass == get_class( $fnObj ) )
						return( true );
			}
		}

		return( false );
	}

	static function RemoveFilters( $tag, $fnRem, $priority = Wp::REMOVEFILTER_PRIORITY_ALL, &$filters = array() )
	{
		global $wp_filter;

		$items = array();
		{
			$flts = $wp_filter;
			if( $tag !== Wp::REMOVEFILTER_TAG_ALL )
			{
				$fltPriors = @$wp_filter[ $tag ];
				if( !$fltPriors )
					return( false );

				$flts = array( $tag => $fltPriors );
			}

			foreach( $flts as $fltTag => $fltPriors )
			{

				if( is_object( $fltPriors )  )
				{
					if( property_exists( $fltPriors, 'callbacks' ) )
						$fltPriors = $fltPriors -> callbacks;
				}

				if( is_array( $fltPriors ) )
					foreach( $fltPriors as $fltPrior => $cbs )
					{
						if( $priority !== self::REMOVEFILTER_PRIORITY_ALL && $fltPrior != $priority )
							continue;

						foreach( $cbs as $cbKey => $cb )
						{
							$fn = $cb[ 'function' ];
							if( self::_RemoveFilter_IsEqual( $fn, $fnRem ) )
								$items[] = array( 't' => $fltTag, 'k' => $cbKey, 'f' => $fn, 'p' => $fltPrior, 'a' => $cb[ 'accepted_args' ] );
						}
					}
			}
		}

		$res = false;

		foreach( $items as &$item )
		{
			if( remove_filter( $item[ 't' ], $item[ 'k' ], $item[ 'p' ] ) )
			{
				$res = true;

				unset( $item[ 'k' ] );
				$filters[] = $item;
			}
		}

		return( $res );
	}

	static function AddFilters( $filters )
	{
		$res = false;

		if( is_array( $filters ) )
			foreach( $filters as $filter )
				if( add_filter( $filter[ 't' ], $filter[ 'f' ], $filter[ 'p' ], $filter[ 'a' ] ) )
					$res = true;

		return( $res );
	}

	const SETPOSTLANG_IDORIG_UNSET			= null;
	const SETPOSTLANG_IDORIG_DONTCHANGE		= -1;

	static function IsLangsActive()
	{
		if( self::_Wpml_IsActive() )
			return( true );

		return( false );
	}

	static function GetDefLang()
	{
		if( self::_Wpml_IsActive() )
			return( self::_Wpml_GetDefLang() );

		return( null );
	}

	static function GetCurLang()
	{
		if( self::_Wpml_IsActive() )
			return( self::_Wpml_GetCurLang() );

		return( null );
	}

	static function SetCurLang( $lang )
	{
		if( self::_Wpml_IsActive() )
			return( self::_Wpml_SetCurLang( $lang ) );

		return( null );
	}

	static function GetLangs()
	{
		if( self::_Wpml_IsActive() )
			return( self::_Wpml_GetLangs() );

		return( null );
	}

	static function GetElemTranslations( $id )
	{

		$res = null;

		if( self::_Wpml_IsActive() )
			$res = self::_Wpml_GetElemTranslations( $id );

		if( empty( $res ) )
			$res = array( $id => null );

		return( $res );
	}

	static function GetPostLang( $id, $postType = null )
	{

		$lang = null;

		if( empty( $postType ) )
			$postType = get_post_type( $id );

		if( self::_Wpml_IsActive() )
			$lang = self::_Wpml_GetElemLang( $id, $postType );

		return( $lang );
	}

	static function GetElemLang( $id, $type )
	{

		$lang = null;

		if( self::_Wpml_IsActive() )
			$lang = self::_Wpml_GetElemLang( $id, $type );

		return( $lang );
	}

	static function SetPostLang( $id, $lang, $idOrig = self::SETPOSTLANG_IDORIG_DONTCHANGE )
	{

		$type = get_post_type( $id );
		$typeOrig = ( $idOrig !== self::SETPOSTLANG_IDORIG_DONTCHANGE ) ? get_post_type( $idOrig ) : null;

		if( self::_Wpml_IsActive() )
			return( self::_Wpml_SetElemLang( $id, $type, $lang, $idOrig, $typeOrig ) );

		return( Gen::E_NOTIMPL );
	}

	static function SetElemLang( $id, $lang, $type, $idOrig = self::SETPOSTLANG_IDORIG_DONTCHANGE, $typeOrig = null )
	{

		if( self::_Wpml_IsActive() )
			return( self::_Wpml_SetElemLang( $id, $type, $lang, $idOrig, $typeOrig ) );

		return( Gen::E_NOTIMPL );
	}

	static function RemoveLangFilters()
	{

		if( self::_Wpml_IsActive() )
			return( self::_Wpml_RemoveLangFilters() );

		return( null );
	}

	static function RemoveLangAttachmentFilters()
	{

		if( self::_Wpml_IsActive() )
			return( self::_Wpml_RemoveLangAttachmentFilters() );

		return( null );
	}

	static private function _Wpml_IsActive()
	{
		return( function_exists( 'icl_object_id' ) );

	}

	static private function _Wpml_GetDefLang()
	{
		global $sitepress;
		return( $sitepress -> get_default_language() );
	}

	static private function _Wpml_GetCurLang()
	{
		return( ICL_LANGUAGE_CODE );
	}

	static private function _Wpml_SetCurLang( $lang )
	{
		global $sitepress;
		$sitepress -> switch_lang( $lang );
	}

	static private function _Wpml_GetLangs()
	{
		$res = array();

		$langCurPrev = self::_Wpml_GetCurLang();
		$langDef = self::_Wpml_GetDefLang();

		if( $langCurPrev != $langDef )
			self::_Wpml_SetCurLang( $langDef );

		$ls = icl_get_languages( 'skip_missing=0' );
		foreach( $ls as $l )
			$res[ $l[ 'language_code' ] ] = $l[ 'translated_name' ];

		if( $langCurPrev != $langDef )
			self::_Wpml_SetCurLang( $langCurPrev );

		return( $res );
	}

	static private function _Wpml_GetElemTranslations( $id )
	{
		global $sitepress;
		if( !$sitepress )
			return( null );

		$res = array();

		$ts = $sitepress -> get_element_translations( $sitepress -> get_element_trid( $id ) );
		foreach( $ts as $tLang => $t )
			$res[ $t -> element_id ] = $tLang;

		return( $res );
	}

	static private function _Wpml_GetElemLang( $id, $type )
	{
		global $sitepress;

		if( empty( $type ) )
			return( null );

		$typeWpml = wpml_element_type_filter( $type );
		if( empty( $typeWpml ) )
			return( null );

		$langInfoPost = $sitepress -> get_element_language_details( $id, $typeWpml );
		if( !$langInfoPost )
			return( null );

		return( $langInfoPost -> language_code );
	}

	static private function _Wpml_SetElemLang( $id, $type, $lang, $idOrig, $typeOrig )
	{
		global $sitepress;

		if( empty( $type ) )
			return( Gen::E_INVALIDARG );

		$typeWpml = wpml_element_type_filter( $type );
		if( empty( $typeWpml ) )
			return( Gen::E_INTERNAL );

		$langTrId = null;
		$sourceLangCode = null;

		if( !empty( $idOrig ) )
		{
			if( $idOrig === self::SETPOSTLANG_IDORIG_DONTCHANGE )
			{
				$langInfoPost = $sitepress -> get_element_language_details( $id, $typeWpml );

				if( $langInfoPost )
				{
					$langTrId = $langInfoPost -> trid;
					$sourceLangCode = $langInfoPost -> source_language_code;
				}
			}
			else
			{
				if( empty( $typeOrig ) || $typeOrig != $type )
					return( Gen::E_INVALIDARG );

				$langInfoPostOrig = $sitepress -> get_element_language_details( $idOrig, $typeWpml );

				if( $langInfoPostOrig )
				{
					$langTrId = $langInfoPostOrig -> trid;
					$sourceLangCode = $langInfoPostOrig -> language_code;
				}
			}
		}

		$sitepress -> set_element_language_details( $id, $typeWpml, $langTrId, $lang, $sourceLangCode, true );

		return( Gen::S_OK );
	}

	static private function _Wpml_RemoveLangAttachmentFilters()
	{

		$filters = array();
		Wp::RemoveFilters( 'add_attachment',				array( 'WPML_Media_Attachments_Duplication', Wp::REMOVEFILTER_FUNCNAME_ALL ), Wp::REMOVEFILTER_PRIORITY_ALL, $filters );
		Wp::RemoveFilters( 'edit_attachment',				array( 'WPML_Media_Attachments_Duplication', Wp::REMOVEFILTER_FUNCNAME_ALL ), Wp::REMOVEFILTER_PRIORITY_ALL, $filters );
		Wp::RemoveFilters( 'save_post',						array( 'WPML_Media_Attachments_Duplication', Wp::REMOVEFILTER_FUNCNAME_ALL ), Wp::REMOVEFILTER_PRIORITY_ALL, $filters );
		return( $filters );
	}

	static private function _Wpml_RemoveLangFilters()
	{

		$filters = array();
		Wp::RemoveFilters( Wp::REMOVEFILTER_TAG_ALL,		array( 'SitePress', Wp::REMOVEFILTER_FUNCNAME_ALL ), Wp::REMOVEFILTER_PRIORITY_ALL, $filters );
		return( $filters );
	}

	const LOC_DEF			= 'en_US';

	static function Loc_ScriptLoad( $handle, $domain, $locSubPath )
	{
		if( Gen::DoesFuncExist( '\\wp_set_script_translations' ) )
			\wp_set_script_translations( $handle, $domain, $path );

		$localeCur = Wp::GetLocale();

		$locales = array( $localeCur );
		if( $localeCur != self::LOC_DEF )
			$locales[] = self::LOC_DEF;

		$locDataFile = null;

		foreach( $locales as $locale )
		{
			$locDataFileProbe = WP_PLUGIN_DIR . '/cryptocurrency-coin-prices/languages/cryptocurrency-coin-prices-' . substr( $handle, strlen( 'cryptocurrency_prices' ) + 1 ) . '-' . $locale . '.json';
			if( @file_exists( $locDataFileProbe ) )
			{
				$locDataFile = $locDataFileProbe;
				break;
			}
		}

		if( empty( $locDataFile ) )
			return( false );

		$translations = self::_LoadScriptTranslations( $locDataFile, $handle, $domain );
		if( empty( $translations ) )
			return( false );

		return( \wp_localize_script( $handle, '_' . $handle . '_scriptLocData', array( 'l10n_print_after' => 'jQuery(document).on("ready",function($){var data = ' . $translations . ';cryptocurrency_prices.Wp.Loc.SetData(data.locale_data.messages,"' . $domain . '");})' ) ) );
	}

	static private function _LoadScriptTranslations( $file, $handle, $domain )
	{
		if( Gen::DoesFuncExist( '\\load_script_translations' ) )
			return( load_script_translations( $file, $handle, $domain ) );

		$file = apply_filters( 'load_script_translation_file', $file, $handle, $domain );
		if( !$file || !is_readable( $file ) )
			return( false );

		return( apply_filters( 'load_script_translations', @file_get_contents( $file ), $file, $handle, $domain ) );
	}

	static function GetLocale()
	{
		return( Gen::DoesFuncExist( 'determine_locale' ) ? determine_locale() : ( is_admin() ? get_user_locale() : get_locale() ) );
	}

	static private $_locLoadCtx = null;

	static function _Loc_LoadTextDomain( $domain, $pathRel )
	{

		return( load_plugin_textdomain( $domain, false, $pathRel ) );

	}

	static function Loc_Load( array $subSystemIds = array( '', 'admin' ), $domain, $locSubPath, array $addFiles = array() )
	{
		if( !count( $subSystemIds ) )
			return( false );

		$pathAbsRoot = WP_PLUGIN_DIR . '/' . $domain;
		$pathRel = $domain . '/' . $locSubPath;
		add_filter( 'plugin_locale', __CLASS__ . '::_on_mofile_locale', 1000, 2 );

		add_filter( 'load_textdomain_mofile', __CLASS__ . '::_on_load_textdomain_mofile', 0, 2 );

		$localeCur = Wp::GetLocale();

		$locales = array( null );
		if( $localeCur != self::LOC_DEF )
			$locales[] = self::LOC_DEF;

		$resLocale = false;

		self::$_locLoadCtx = array( 'pathAbsRoot' => $pathAbsRoot );

		foreach( $subSystemIds as $subSystemId )
		{
			self::$_locLoadCtx[ 'subSystemIdCur' ] = $subSystemId;

			$subSystemLoaded = false;
			$pathAbsRootTouched = false;

			$addFilesLoaded = array();

			foreach( $locales as $locale )
			{
				self::$_locLoadCtx[ 'localeCur' ] = $locale;
				self::$_locLoadCtx[ 'pathAbsRootTouched' ] = false;

				if( !$subSystemLoaded )
				{
					if( self::_Loc_LoadTextDomain( $domain, $pathRel ) )
						$subSystemLoaded = true;

					$pathAbsRootTouched = self::$_locLoadCtx[ 'pathAbsRootTouched' ];
					if( $subSystemLoaded && !$pathAbsRootTouched )
					{
						self::$_locLoadCtx[ 'forceLoadOwn' ] = true;
						$pathAbsRootTouched = self::_Loc_LoadTextDomain( $domain, $pathRel );
						self::$_locLoadCtx[ 'forceLoadOwn' ] = false;
					}
				}

				if( !empty( $addFiles ) )
				{
					$localeFilePart = apply_filters( 'plugin_locale', $localeCur, $domain );

					foreach( $addFiles as $addFileIdx => $addFile )
					{
						$mofile = $pathAbsRoot . '/' . $addFile . '-' . $localeFilePart . '.mo';
						if( !@$addFilesLoaded[ $addFileIdx ] && load_textdomain( $domain, $mofile ) )
							$addFilesLoaded[ $addFileIdx ] = true;
					}
				}

				if( !$resLocale && ( $subSystemLoaded || count( $addFilesLoaded ) == count( $addFiles ) ) )
				{
					$resLocale = self::$_locLoadCtx[ 'localeCur' ];
					if( !$resLocale )
						$resLocale = $localeCur;
				}

				if( $subSystemLoaded && !$pathAbsRootTouched )
					$subSystemLoaded = false;
			}
		}

		self::$_locLoadCtx = null;

		remove_filter( 'load_textdomain_mofile', __CLASS__ . '::_on_load_textdomain_mofile', 0 );

		remove_filter( 'plugin_locale', __CLASS__ . '::_on_mofile_locale', 1000 );

		return( $resLocale );
	}

	static function _on_mofile_locale( $locale, $domain )
	{
		if( !empty( self::$_locLoadCtx[ 'localeCur' ] ) )
			$locale = self::$_locLoadCtx[ 'localeCur' ];

		$locale = $locale . '.SPECLOC';

		if( !empty( self::$_locLoadCtx[ 'subSystemIdCur' ] ) )
			$locale = self::$_locLoadCtx[ 'subSystemIdCur' ] . '-' . $locale;

		return( $locale );
	}

	static function _on_load_textdomain_mofile( $mofile, $domain )
	{
		$pathAbsRoot = self::$_locLoadCtx[ 'pathAbsRoot' ];
		$pathAbsRootTouched = substr( $mofile, 0, strlen( $pathAbsRoot ) ) == $pathAbsRoot;

		if( $pathAbsRootTouched )
			self::$_locLoadCtx[ 'pathAbsRootTouched' ] = true;
		else if( self::$_locLoadCtx[ 'forceLoadOwn' ] )
			return( null );

		return( str_replace( '.SPECLOC', '', $mofile ) );
	}
}

