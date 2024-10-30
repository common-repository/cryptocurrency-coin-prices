<?php

namespace cryptocurrency_prices;

if( !defined( 'ABSPATH' ) )
	exit;

class PluginOptions
{
	static private $_cache = NULL;

	static function Get( $ver, $name, $cbNs = '' )
	{
		$data = is_array( self::$_cache ) ? @self::$_cache[ $name ] : NULL;
		if( $data )
			return( $data );

		$data = get_option( 'cryptocurrency_prices_' . $name );

		$vFrom = is_array( $data ) ? @$data[ 'v' ] : NULL;

		{
			$funcName = $cbNs . 'OnOptRead_' . $name;
			if( Gen::DoesFuncExist( $funcName ) )
				$data = call_user_func( $funcName, $data, $vFrom );
		}

		$data[ 'v' ] = $ver;
		if( $ver != $vFrom )
			self::Set( $ver, $name, $data, $cbNs );

		self::$_cache[ $name ] = $data;
		return( $data );
	}

	static function Set( $ver, $name, $data, $cbNs = '' )
	{

		{
			$funcName = $cbNs . 'OnOptWritePrep_' . $name;
			if( Gen::DoesFuncExist( $funcName ) )
				$data = call_user_func( $funcName, $data );
		}

		$data[ 'v' ] = $ver;

		self::$_cache[ $name ] = $data;

		$dataWrite = $data;
		{
			$funcName = $cbNs . 'OnOptWrite_' . $name;
			if( Gen::DoesFuncExist( $funcName ) )
				$dataWrite = call_user_func( $funcName, $dataWrite );
		}

		$hr = update_option( 'cryptocurrency_prices_' . $name, $dataWrite ) ? Gen::S_OK : Gen::S_FALSE;

		return( $hr );
	}

	static function Del( $name )
	{
		unset( self::$_cache[ $name ] );
		delete_option( 'cryptocurrency_prices_' . $name );
	}
}

class PluginPostOptions
{
	static private $_cache = NULL;

	static function Get( $postId, $ver, $name, $cbType = 'Post', $cbNs = '' )
	{
		$cachePath = array( $cbType, $postId, $name );

		$data = Gen::GetArrField( self::$_cache, $cachePath );
		if( $data )
			return( $data );

		$data = get_post_meta( $postId, '_cryptocurrency_prices_' . $name, true );
		if( !is_array( $data ) )
			$data = array();

		$vFrom = @$data[ 'v' ];

		{
			$funcName = $cbNs . 'On' . $cbType . 'OptRead_' . $name;
			if( Gen::DoesFuncExist( $funcName ) )
				$data = call_user_func( $funcName, $data, $vFrom );
		}

		$data[ 'v' ] = $ver;
		if( $ver != $vFrom )
			self::Set( $postId, $ver, $name, $data, $cbType, $cbNs );

		Gen::SetArrField( self::$_cache, $cachePath, $data );
		return( $data );
	}

	static function Set( $postId, $ver, $name, $data, $cbType = 'Post', $cbNs = '' )
	{
		$cachePath = array( $cbType, $postId, $name );

		$data[ 'v' ] = $ver;

		{
			$funcName = $cbNs . 'On' . $cbType . 'OptWritePrep_' . $name;
			if( Gen::DoesFuncExist( $funcName ) )
				$data = call_user_func( $funcName, $data );
		}

		Gen::SetArrField( self::$_cache, $cachePath, $data );

		$dataWrite = $data;
		{
			$funcName = $cbNs . 'On' . $cbType . 'OptWrite_' . $name;
			if( Gen::DoesFuncExist( $funcName ) )
				$dataWrite = call_user_func( $funcName, $dataWrite );
		}

		return( update_post_meta( $postId, '_cryptocurrency_prices_' . $name, $dataWrite ) );
	}

	static function Del( $postId, $name, $cbType = 'Post' )
	{
		$cachePath = array( $cbType, $postId, $name );

		Gen::SetArrField( self::$_cache, $cachePath, null );
		delete_post_meta( $postId, '_cryptocurrency_prices_' . $name );
		return( true );
	}
}

class PluginRmtCfg
{
	static function Update( $bForce = false, $bFirstTimeOnly = false )
	{

		return( Gen::S_FALSE );

	}

	static function Get()
	{

		return( array() );

	}

}

class Plugin
{
	const BASENAME			= 'cryptocurrency-coin-prices/plugin_root.php';

	const STATE_VER			= 1;

	const DisplayContent_Str		= 0;
	const DisplayContent_SmallBlock	= 1;
	const DisplayContent_Block		= 2;

	static private $_locale = null;

	static function Init()
	{
		$isAdminMode = is_admin();

		if( $isAdminMode )
		{
			PluginRmtCfg::Update( false, true );
			add_action( 'admin_notices', __CLASS__ . '::_on_admin_notices' );
		}

		add_filter( 'site_transient_update_plugins', __CLASS__ . '::_on_site_transient_update_plugins', 10, 2 );

		add_action( 'plugins_loaded',
			function()
			{
				$subSystemIds = array();

				$subSystemIds[] = '';

				if( is_admin() )
				{
					if( !array_search( '', $subSystemIds, true ) )
						$subSystemIds[] = '';
					$subSystemIds[] = 'admin';
				}

				self::$_locale = Wp::Loc_Load( $subSystemIds, 'cryptocurrency-coin-prices', 'languages', apply_filters( 'cryptocurrency_prices_onLocLoadAddFiles', array(), 'languages' ) );
			}
		);

		if( $isAdminMode )
		{
			add_action( 'admin_action_cryptocurrency_prices_act', __CLASS__ . '::_on_admin_action_act' );

			add_filter( 'removable_query_args',
				function( $args )
				{
					$args[] = 'cryptocurrency_prices_postOpsRes';
					return( $args );
				}
			);

			add_action( 'admin_init',
				function()
				{

				}
			);

			add_action( 'admin_enqueue_scripts',
				function( $hook )
				{
					echo( Plugin::CmnStyle( 'AdminUi' ) );
				}
			);

		}

		if( !self::IsEulaAccepted() )
			return;

		{
			$state = PluginOptions::Get( self::STATE_VER, 'State', __CLASS__ . '::' );
			if( !isset( $state[ 'firstUseTimeStamp' ] ) )
			{
				$state[ 'firstUseTimeStamp' ] = time();
				PluginOptions::Set( self::STATE_VER, 'State', $state, __CLASS__ . '::' );
			}
		}

		add_filter( 'do_parse_request', __CLASS__ . '::_on_parse_request', 0, 1 );
		add_filter( 'plugins_update_check_locales', __CLASS__ . '::_on_check_plugins_updates', 10, 1 );

		if( $isAdminMode )
		{
			add_action( 'admin_action_cryptocurrency_prices_api',
				function()
				{
					$apiFunc = @$_REQUEST[ 'fn' ];
					if( empty( $apiFunc ) )
						return;

					unset( $_REQUEST[ 'action' ] );
					unset( $_REQUEST[ 'fn' ] );

					self::_ApiCall_Make( 'cryptocurrency_prices\\OnAdminApi_' . $apiFunc, $_REQUEST );
					exit();
				}
			);

			add_filter( 'plugin_action_links_' . Plugin::BASENAME,
				function( $actions, $plugin_file )
				{
					if( !is_array( $actions ) )
						return( $actions );

					$rmtCfg = PluginRmtCfg::Get();

					Plugin::ActionsListAdd( $actions, 'docs', Ui::Link( _x( 'PluginDocLink', 'admin.Common', 'cryptocurrency-coin-prices' ), Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Links.UrlProductDocs' ), true, array( 'noTextIfNoHref' => true ) ), true );
					if( !Gen::IsEmpty( $urlSettings = menu_page_url( 'cryptocurrency_prices_settings', false ) ) )
						Plugin::ActionsListAdd( $actions, 'settings', Ui::Link( Wp::GetLocString( 'Settings' ), $urlSettings ), true );

					if( !Gen::IsEmpty( Gen::GetArrField( $rmtCfg, 'Prms.FullProductDownloadPath' ) ) )
						Plugin::ActionsListAdd( $actions, 'order', Ui::Link( _x( 'OrderInLockedFeatureBtn', 'admin.Common_SwitchTo', 'cryptocurrency-coin-prices' ), Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Links.UrlProductBuy' ), true, array( 'noTextIfNoHref' => true ), array( 'style' => array( 'font-weight' => '900' ) ) ), true );

					$actDeactivate = @$actions[ 'deactivate' ];
					if( $actDeactivate )
					{
						$q = Gen::GetArrField( $rmtCfg, 'Questionnaires.Items.Deactivate' );
						if( $q )
						{

							$href = null;
							{
								$ndAct = HtmlNd::FindByTag( HtmlNd::Parse( $actDeactivate ), 'a' );
								HtmlNd::SetAttrVal( $ndAct, 'onclick', 'cryptocurrency_prices.Ui.PopupShow(\'deactivateQuestionnaire\');return(false);' );

								{
									$res = HtmlNd::DeParse( $ndAct );
									if( !empty( $res ) )
										$actions[ 'deactivate' ] = $res;
								}

								$href = HtmlNd::GetAttrVal( $ndAct, 'href' );
							}

							UiPopups::Add( 'deactivateQuestionnaire',
								array(
									'modal' => true,
									'attrs' => array( 'class' => 'metabox-holder', 'style' => array( 'max-width' => '500px' ) ),
									'q' => array( 'id' => 'Deactivate', 'params' => $q ),
									'href' => $href,
									'cbPre' => function( $prms ){ self::_admin_printscriptsstyles(); },
									'cb' => __CLASS__ . '::_OnPopup_QuestionnaireDeactivate'
								)
							);
						}
					}

					return( $actions );
				}
			, 10, 2 );

			add_action( 'admin_footer',
				function()
				{
					UiPopups::Draw();
				}
			);

			if( Gen::DoesFuncExist( 'cryptocurrency_prices\\OnInitAdminMode' ) )
				call_user_func( 'cryptocurrency_prices\\OnInitAdminMode' );
		}

		return( array( 'isAdmin' => $isAdminMode ) );

		__( 'Crypto Coin Market Prices', 'cryptocurrency-coin-prices' );
		__( 'Easy to use option for setting up a bitcoin and altcoin exchange rate.', 'cryptocurrency-coin-prices' );
		__( 'https://www.mybitcoin.com', 'cryptocurrency-coin-prices' );
		__( 'MyBitcoin', 'cryptocurrency-coin-prices' );
		__( 'http://wordpress.org/plugins/cryptocurrency-coin-prices', 'cryptocurrency-coin-prices' );
	}

	static function Loc_ScriptLoad( $handle )
	{
		return( Wp::Loc_ScriptLoad( $handle, 'cryptocurrency-coin-prices', 'languages' ) );
	}

	static function ReloadWithPostOpRes( $res, $redir = null )
	{
		if( $redir === null )
			$redir = wp_get_referer();
		wp_redirect( add_query_arg( array( 'cryptocurrency_prices_postOpsRes' => urlencode( base64_encode( json_encode( $res ) ) ) ), $redir ) );
	}

	static function ActionsListAdd( &$actions, $id, $link, $first = false )
	{
		if( empty( $link ) )
			return;

		if( $first )
			$actions = array_merge( array( $id => $link ), $actions );
		else
			$actions = array_merge( $actions, array( $id => $link ) );
	}

	static private $_IsEulaAccepted = null;

	static function IsPaidLockedContent()
	{

		return( true );
	}

	static function AdminBtnsBlock_GetPaidContent()
	{
		if( !self::IsPaidLockedContent() )
			return( null );

		$rmtCfg = PluginRmtCfg::Get();
		return( array( 'type' => Ui::AdminBtn_Paid, 'href' => Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Links.UrlProductBuy' ) ) );
	}

	static function IsEulaAccepted()
	{

		return( true );

	}

	static private function _IsEulaAccepted()
	{
		if( self::$_IsEulaAccepted === null )
		{
			$state = PluginOptions::Get( self::STATE_VER, 'State', __CLASS__ . '::' );
			self::$_IsEulaAccepted = $state[ 'eulaAcceptedVer' ] == namespace\PLUGIN_EULA_VER;
		}

		return( self::$_IsEulaAccepted );
	}

	static function GetSwitchToExtTitle()
	{

		return( _x( 'ExtTitle', 'admin.Common_SwitchTo', 'cryptocurrency-coin-prices' ) );

	}

	static function SwitchToExt( $type = Plugin::DisplayContent_Block, $text = null )
	{

		$rmtCfg = PluginRmtCfg::Get();

		$dwnldUrl = Gen::GetArrField( $rmtCfg, 'Links.UrlProductDownload' );
		if( !$dwnldUrl )
			return;

		$res = '';
		if( !empty( $text ) )
			$res .= $text . ' ';
		$res .= vsprintf( _x( 'ExtInfo_%1$s%2$s', 'admin.Common_SwitchTo', 'cryptocurrency-coin-prices' ), Ui::Link( Ui::Tag( 'strong', array( '', '' ) ), Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Links.UrlProductFeatures' ), true ) );

		return( Plugin::_GetSwitchToContent( $rmtCfg, $type == Plugin::DisplayContent_SmallBlock ? _x( 'ExtSmallBtn', 'admin.Common_SwitchTo', 'cryptocurrency-coin-prices' ) : _x( 'ExtBtn', 'admin.Common_SwitchTo', 'cryptocurrency-coin-prices' ), 'ext', $res ) );

	}

	static function _GetSwitchToContent( $rmtCfg, $switchBtnName, $mode, $text = null )
	{
		$res = Ui::TagOpen( 'p' );
		if( !empty( $text ) )
			$res .= $text;

		if( !self::_IsEulaAccepted() )
		{
			if( !empty( $res ) )
				$res .= ' ';
			$res .= vsprintf( _x( 'LicAcceptInfo_%1$s%2$s%3$s', 'admin.Common_SwitchTo', 'cryptocurrency-coin-prices' ), Gen::ArrFlatten( array( $switchBtnName, Ui::Link( array( '', '' ), Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Links.UrlEula' ), true ) ) ) );
		}

		$res .= Ui::TagClose( 'p' );

		$res .= Ui::Tag( 'p', _x( 'UpgrInfo', 'admin.Common_SwitchTo', 'cryptocurrency-coin-prices' ), array( 'class' => array( 'description', 'ctlSpaceVBefore' ) ) );

		$res .= Ui::TagOpen( 'div', array( 'class' => 'ctlSpaceVBefore cryptocurrency_prices_switchto' ) );
		{
			$res .= Ui::SettBlock_ItemSubTbl_Begin( array( 'class' => 'std operateblock' ) ) . Ui::TagOpen( 'tr' );
			{
				$res .= Ui::Tag( 'td', Ui::Button( $switchBtnName, true, null, 'cryptocurrency_prices_btnok', 'button', array( 'onclick' => '_cryptocurrency_prices_SwitchTo(\'' . $mode . '\')' ) ), array( 'class' => 'ctlVaMiddle' ) );
				$res .= Ui::Tag( 'td', Ui::CheckBox( _x( 'RefreshChk', 'admin.Common_SwitchTo', 'cryptocurrency-coin-prices' ), null, true, false, null, null, array( 'class' => 'refresh_after' ) ), array( 'class' => 'ctlVaMiddle' ) );
			}
			$res .= Ui::TagClose( 'tr' ) . Ui::SettBlock_ItemSubTbl_End();

			$res .= Ui::Tag( 'div', Ui::Tag( 'p', null, array( 'aria-label' => '' ) ), array( 'style' => array( 'display' => 'none', 'margin-bottom' => 0 ), 'class' => 'ctlSpaceVBefore update-message notice inline notice-alt' ) );

			ob_start();

?>

			<script>
				function _cryptocurrency_prices_SwitchTo( mode )
				{
					var block = jQuery( ".cryptocurrency_prices_switchto" );
					var msg = block.find( ".update-message" );
					msg.removeClass( "notice-success notice-error updated-message" );
					msg.addClass( "notice-warning updating-message" );
					msg.find( "p" ).text( "<?php echo( _x( 'UpgrProgressInfo', 'admin.Common_SwitchTo', 'cryptocurrency-coin-prices' ) ); ?>" );
					block.find( ".operateblock" ).find( "*" ).prop( "disabled", true );
					msg.show();

					function _Finish( ok, text )
					{
						if( !ok )
							block.find( ".operateblock" ).find( "*" ).prop( "disabled", false );
						else
							block.find( ".operateblock" ).hide();
						msg.removeClass( "notice-warning updating-message" );
						msg.addClass( ok ? "notice-success updated-message" : "notice-error" );
						msg.find( "p" ).text( text );

						if( ok && block.find( ".refresh_after" ).prop( "checked" ) )
							setTimeout( function() { location.reload(); }, 0 );
					}

					jQuery.ajax( { url: "<?php echo( add_query_arg( array( 'action' => 'cryptocurrency_prices_act', 'fn' => 'acceptEula' ), get_admin_url( NULL, 'admin.php' ) ) ); ?>", type: "post" } )
					.then(
						function( res )
						{
							return( jQuery.ajax(
								{
									url: "<?php echo( get_admin_url( NULL, 'admin-ajax.php' ) ); ?>",
									type: "post",
									data:
									{
										action: "update-plugin",
										plugin:"<?php echo( Plugin::BASENAME ); ?>",
										slug: "cryptocurrency-coin-prices",
										_ajax_nonce:"<?php echo( wp_create_nonce( 'updates' ) ); ?>",
										cryptocurrency_prices_switchto: mode
									},
									dataType: "json"
								}
							) );
						}
					)
					.then(
						function( res )
						{
							if( res.success )
								_Finish( true, "<?php echo( _x( 'UpgrOkInfo', 'admin.Common_SwitchTo', 'cryptocurrency-coin-prices' ) ); ?>" );
							else
								_Finish( false, res.data.errorMessage );

						},

						function( res )
						{
							_Finish( false, res.statusText === "OK" ? ( "PHP error: " + res.responseText ) : ( "Backend error: " + res.status + " " + res.statusText/* + ": " + res.responseText*/ ) );
						}
					);
				}
			</script>
			
			<?php

			$res .= ob_get_clean();
		}
		$res .= Ui::TagClose( 'div' );

		return( $res );
	}

	static function DisplayAdminFooterRateItContent()
	{
		if( self::_RateIt_ShouldShow() !== false )
			add_filter( 'admin_footer_text', __CLASS__ . '::_OnFilter_AdminFooterText', 10, 1 );
	}

	const URL_RATEIT		= 'https://wordpress.org/support/plugin/cryptocurrency-coin-prices/reviews?rate=5#new-post';

	static function GetRateItTitle()
	{
		return( _x( 'Title', 'admin.Common_RateIt', 'cryptocurrency-coin-prices' ) );
	}

	static function GetRateItContent( $blockId, $type = Plugin::DisplayContent_Block )
	{
		if( self::_RateIt_ShouldShow() === false )
			return( null );

		$res = '';

		$res .= self::_GetRateItContent( $type );

		$res .= '<br/><br/>' . Ui::Button( _x( 'RateSmallBtn', 'admin.Common_RateIt', 'cryptocurrency-coin-prices' ), true, null, 'ctlSpaceAfter', 'button', array( 'onclick' => 'window.open(\'' . self::URL_RATEIT . '\', \'_blank\')' ) );

		$res .= Ui::Tag( 'span',
			Ui::Tag( 'input', null, array( 'type' => 'button', 'class' => 'button-link', 'style' => array( 'margin-right' => '1em', 'vertical-align' => 'middle' ), 'value' => _x( 'AlreadyRatedBtn', 'admin.Common_RateIt', 'cryptocurrency-coin-prices' ), 'onclick' => 'cryptocurrency_prices_RateItCont_Set(\'' . $blockId . '\',false);return false;' ) ),
			array( 'class' => 'actions' )
		);

		$res .= Ui::Tag( 'span', null, array( 'class' => 'cryptocurrency_prices_spinner', 'style' => array( 'display' => 'none', 'vertical-align' => 'middle' ) ) );

		ob_start();

		?>

		<script>
			function cryptocurrency_prices_RateItCont_Set( blockId, mode )
			{
				var block = jQuery( "#" + blockId );
				var blockActs = block.find( ".actions" );
				var blockSpinner = block.find( ".cryptocurrency_prices_spinner" );
				
				blockActs.hide();
				blockSpinner.show();

				jQuery.ajax(
					{
						url: "<?php echo( add_query_arg( array( 'action' => 'cryptocurrency_prices_act', 'fn' => '' ), get_admin_url( NULL, 'admin.php' ) ) ); ?>=" + ( mode ? "rateItPostpone" : "rateItDisable" ),
						type: "post"
					}
				).then(
					function( res )
					{
						block.hide();
					},

					function( res )
					{
						blockActs.show();
						blockSpinner.hide();
					}
				);
			}
		</script>

		<?php

		$res .= ob_get_clean();

		return( $res );
	}

	static function _GetRateItContent( $type )
	{
		$rmtCfg = PluginRmtCfg::Get();

		$res = vsprintf( _x( 'Info_%1$s%2$s%3$s%4$s%5$s', 'admin.Common_RateIt', 'cryptocurrency-coin-prices' ), Gen::ArrFlatten( array(
			Ui::Link( '&#9733;&#9733;&#9733;&#9733;&#9733;', self::URL_RATEIT, true ),
			$type == Plugin::DisplayContent_Str ? '' : ' ' . _x( 'Info_P2', 'admin.Common_RateIt', 'cryptocurrency-coin-prices' ),
			$type == Plugin::DisplayContent_SmallBlock ? '<br/><br/>' : ' ',
			Ui::Link( array( '', '' ), Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Links.UrlRatingAboutUs' ), true )
		) ) );

		return( $res );
	}

	static function GetNavMenuTitle()
	{
		return( Plugin::GetPluginString( 'Title' ) );
	}

	static function GetSettingsTitle()
	{
		return( Plugin::GetSubjectTitle( Wp::GetLocString( 'Settings' ) ) );
	}

	static function GetSubjectTitle( $name )
	{
		return( sprintf( _x( 'PluginSubjectTitle_%1$s%2$s', 'admin.Common', 'cryptocurrency-coin-prices' ), Plugin::GetPluginString( 'TitleLong' ), $name ) );
	}

	static function GetPluginString( $id )
	{
		$id = 'Plugin' . $id;
		return( _x( $id, 'admin.Common', 'cryptocurrency-coin-prices' ) );

		_x( 'PluginTitle', 'admin.Common', 'cryptocurrency-coin-prices' );
		_x( 'PluginTitleLong', 'admin.Common', 'cryptocurrency-coin-prices' );
		_x( 'PluginTitleFull', 'admin.Common', 'cryptocurrency-coin-prices' );

		_x( 'PluginDescription', 'admin.Common', 'cryptocurrency-coin-prices' );
		_x( 'PluginDescriptionFull', 'admin.Common', 'cryptocurrency-coin-prices' );
	}

	static function GetAboutPluginTitle()
	{
		return( _x( 'Title', 'admin.Common_About', 'cryptocurrency-coin-prices' ) );
	}

	static function GetAboutPluginContent()
	{
		$rmtCfg = PluginRmtCfg::Get();

		$urlProductInfo = Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Links.UrlProductInfo' );
		$urlAboutPluginImg = file_exists( dirname( __FILE__ ) . '/../Images/ProductLogo.png' ) ? Plugin::FileUri( '../Images/ProductLogo.png', __FILE__ ) : null;
		$urlAboutPluginDocs = Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Links.UrlProductDocs' );
		$urlAboutPluginSupport = Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Links.UrlProductSupport' );
		$url3rdPartySoft = file_exists( dirname( __FILE__ ) . '/../third-party-software.html' ) ? Plugin::FileUri( '../third-party-software.html', __FILE__ ) : null;

		$urlEula = null;

		$res = '';

		$res .= Ui::Tag( 'p' );

		{
			$version = esc_html( '1.0.1' );

			$res .= Ui::TagOpen( 'div' );

			if( !empty( $urlAboutPluginImg ) )
				$res .= Ui::Link( Ui::Tag( 'img', null, array( 'class' => 'ctlSpaceAfter', 'width' => 100, 'style' => array( 'float' => 'left' ), 'src' => $urlAboutPluginImg ), true ), $urlProductInfo, true );

			$res .= '<h3 style="margin:0">' . _x( 'PluginTitleFull', 'admin.Common', 'cryptocurrency-coin-prices' ) . '</h3>';

			$res .= Ui::Tag( 'div', vsprintf( esc_html( _x( 'Version_%1$s%2$s%3$s', 'admin.Common_About', 'cryptocurrency-coin-prices' ) ), Gen::ArrFlatten( array( Ui::Tag( 'strong', array( '', '' ) ), $version ) ) ), array( 'class' => 'pluginVersion', 'style' => array( 'margin-top' => '0.3em' ) ) );

			$res .= Ui::TagClose( 'div' );
		}

		$res .= Ui::Tag( 'p', _x( 'PluginDescriptionFull', 'admin.Common', 'cryptocurrency-coin-prices' ) );

		{
			$linksPrms = array( 'noTextIfNoHref' => true, 'linkPreContent' => Ui::TagOpen( 'span', array( 'style' => array( 'display' => 'block' ) ) ), 'linkAfterContent' => Ui::TagClose( 'span' ) );

			$resPart = '';

			$resPart .= Ui::Link( _x( 'PluginDocLink', 'admin.Common', 'cryptocurrency-coin-prices' ), $urlAboutPluginDocs, true, $linksPrms );
			$resPart .= Ui::Link( _x( 'LinkSupport', 'admin.Common_About', 'cryptocurrency-coin-prices' ), $urlAboutPluginSupport, true, $linksPrms );

			$resPart .= Ui::Link( _x( 'Link3rdPartySoft', 'admin.Common_About', 'cryptocurrency-coin-prices' ), $url3rdPartySoft, true, $linksPrms );

			$res .= Ui::Tag( 'p', $resPart, null, false, array( 'noTagsIfNoContent' => true ) );
		}

		return( $res );
	}

	static function GetAboutVendorTitle()
	{
		return( _x( 'Title', 'admin.Common_AboutVendor', 'cryptocurrency-coin-prices' ) );
	}

	static function GetAboutVendorContent()
	{
		$rmtCfg = PluginRmtCfg::Get();

		$urlAboutUsLogoImg = file_exists( dirname( __FILE__ ) . '/../Images/VendorLogo.png' ) ? Plugin::FileUri( '../Images/VendorLogo.png', __FILE__ ) : null;
		$urlMorePlugins = Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Links.UrlMorePlugins' );
		$urlMoreInfo = Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Links.UrlMain' );

		$res = '';

		if( !empty( $urlAboutUsLogoImg ) )
			$res .= Ui::Tag( 'p', Ui::Link( Ui::Tag( 'img', null, array( 'src' => $urlAboutUsLogoImg ), true ), $urlMoreInfo, true ) );

		$res .= Ui::Tag( 'p', _x( 'Info1', 'admin.Common_AboutVendor', 'cryptocurrency-coin-prices' ) );

		$res .= Ui::Tag( 'p', vsprintf( _x( 'Info2_%1$s%2$s%3$s%4$s%5$s%6$s', 'admin.Common_AboutVendor', 'cryptocurrency-coin-prices' ), Gen::ArrFlatten( array(
			Ui::Link( Ui::Tag( 'strong', array( '', '' ) ), Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Links.UrlProductSupport' ), true ),
			Ui::Tag( 'strong', array( '', '' ) ),
			Ui::Tag( 'strong', array( '', '' ) )
		) ) ) );

		{
			$resPart = '';

			if( !empty( $urlMorePlugins ) )
				$resPart .= Ui::Button( _x( 'MorePluginsBtn', 'admin.Common_AboutVendor', 'cryptocurrency-coin-prices' ), false, null, 'ctlSpaceAfter', 'button', array( 'onclick' => 'window.open( \'' . $urlMorePlugins . '\', \'_blank\' )' ) );

			if( !empty( $urlMoreInfo ) )
				$resPart .= Ui::Button( _x( 'MoreInfoBtn', 'admin.Common_AboutVendor', 'cryptocurrency-coin-prices' ), false, null, 'ctlSpaceAfter', 'button', array( 'onclick' => 'window.open( \'' . $urlMoreInfo . '\', \'_blank\' )' ) );

			$res .= Ui::Tag( 'p', $resPart, null, false, array( 'noTagsIfNoContent' => true ) );
		}

		return( $res );
	}

	static function GetSettingsLicenseTitle()
	{

		return( null );

	}

	static function GetSettingsLicenseContent()
	{

		return( null );

	}

	static function GetSwitchToFullTitle()
	{

		return( null );

	}

	static function GetLockedFeatureLicenseContent( $type = Plugin::DisplayContent_Block, $contentBefore = '', $contentInside = '' )
	{

		return( null );

	}

	static function GetAdvertProductsTitle()
	{
		return( _x( 'Title', 'admin.Common_AdvertProducts', 'cryptocurrency-coin-prices' ) );
	}

	const ADVERTPRODUCTS_COLS_NUM = 2;
	const ADVERTPRODUCTS_IMG_WIDTH = 100;

	static function GetAdvertProductsContent( $idBlock )
	{

		$rmtCfg = PluginRmtCfg::Get();

		$urlRequest = Gen::GetArrField( $rmtCfg, 'Prms.UrlSpecialApi' );
		if( empty( $urlRequest ) )
			return( '' );

		$urlMorePlugins = Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Links.UrlMorePlugins' );

		Plugin::CmnScripts( array( 'Cmn', 'Gen', 'Ui', 'Net' ) );

		$res = '';

		$switchHtmlContent = Plugin::SwitchToExt();
		if( !empty( $switchHtmlContent ) )
			$res .= Ui::Tag( 'p', $switchHtmlContent ) . Ui::Tag( 'p', null, array( 'class' => array( 'hndle' ) ) );

		$licHtmlContent = Plugin::GetLockedFeatureLicenseContent( Plugin::DisplayContent_Block, '', vsprintf( _x( 'LockedFeatureInsideInfo_%1$s%2$s%3$s%4$s', 'admin.Common_AdvertProducts', 'cryptocurrency-coin-prices' ), Gen::ArrFlatten( array( Ui::Tag( 'strong', array( '', '' ) ), Ui::Tag( 'strong', array( '', '' ) ) ) ) ) );
		if( !empty( $licHtmlContent ) )
			$res .= Ui::Tag( 'p', $licHtmlContent ) . Ui::Tag( 'p', null, array( 'class' => array( 'hndle' ) ) );

		$res .= Ui::Tag( 'div',
			Ui::Tag( 'div', Ui::Tag( 'span', null, array( 'class' => array( 'cryptocurrency_prices_spinner', 'big' ), 'style' => array( 'vertical-align' => 'middle' ) ) ), array( 'style' => array( 'text-align' => 'center' ) ) ),
			array( 'class' => 'content' ) );

		$res .= Ui::Tag( 'p', vsprintf( _x( 'TotalInfo_%1$s%2$s', 'admin.Common_AdvertProducts', 'cryptocurrency-coin-prices' ), Ui::Link( Ui::Tag( 'strong', array( '', '' ) ), $urlMorePlugins, true ) ), array( 'style' => array( 'text-align' => 'center' ) ) );
		if( !empty( $urlMorePlugins ) )
			$res .= Ui::Tag( 'p', Ui::Button( _x( 'MorePluginsBtn', 'admin.Common_AboutVendor', 'cryptocurrency-coin-prices' ), false, null, 'ctlSpaceAfter', 'button', array( 'onclick' => 'window.open( \'' . $urlMorePlugins . '\', \'_blank\' )' ) ), array( 'style' => array( 'text-align' => 'center' ) ) );

		ob_start();

?>

		<script>
			jQuery( document ).on( 'ready',
				function( $ )
				{
					function _GetItemImageUrl( images, widthTarged )
					{
						if( !Array.isArray( images ) )
							return( null );

						var itemBestWidthDiffPositive = { idx: -1, diff: 0x7FFFFFFF };
						var itemBestWidthDiffNegative = { idx: -1, diff: 0x7FFFFFFF };
						
						for( var i = 0; i < images.length; i++ )
						{
							var image = images[ i ];
							
							if( widthTarged == image.width )
							{
								itemBestWidthDiffPositive.idx = i;
								itemBestWidthDiffPositive.diff = 0;
								break;
							}
							
							if( widthTarged < image.width )
							{
								var diff = image.width - widthTarged;
								if( diff < itemBestWidthDiffPositive.diff )
								{
									itemBestWidthDiffPositive.idx = i;
									itemBestWidthDiffPositive.diff = diff;
								}
							}
							else
							{
								var diff = widthTarged - image.width;
								if( diff < itemBestWidthDiffNegative.diff )
								{
									itemBestWidthDiffNegative.idx = i;
									itemBestWidthDiffNegative.diff = diff;
								}
							}
						}

						if( itemBestWidthDiffPositive.idx != -1 )
							return( images[ itemBestWidthDiffPositive.idx ].url );
						if( itemBestWidthDiffNegative.idx != -1 )
							return( images[ itemBestWidthDiffNegative.idx ].url );
						return( null );
					}
					
					function _GetItemContent( item, imgWidthTarged )
					{
						var content = "";
						
						var urlImage = _GetItemImageUrl( item.images, imgWidthTarged );
						if( urlImage )
							content += cryptocurrency_prices.Ui.Tag( "td", cryptocurrency_prices.Ui.Link( cryptocurrency_prices.Ui.Tag( "img", null, { "class": "ctlSpaceAfter", "width": imgWidthTarged, "src": urlImage }, true ), item.urlInfo, true ) );
						
						content += cryptocurrency_prices.Ui.TagOpen( "td" );
						{
							content += cryptocurrency_prices.Ui.Tag( "h3", item.title, { "class": "ctlSpaceVAfter", style: { "margin-top": 0 } } );
							content += cryptocurrency_prices.Ui.Tag( "div", item.descr );

							if( item.urlInfo )
								content += cryptocurrency_prices.Ui.Button( "<?php echo( _x( 'ItemMoreInfoBtn', 'admin.Common_AdvertProducts', 'cryptocurrency-coin-prices' ) ); ?>", true, null, "ctlSpaceVBefore", "button", { onclick: "window.open( '" + item.urlInfo + "', '_blank' )" } );
						}
						content += cryptocurrency_prices.Ui.TagClose( "td" );

						return( cryptocurrency_prices.Ui.Tag( "table", cryptocurrency_prices.Ui.Tag( "tbody", cryptocurrency_prices.Ui.Tag( "row", content ) ) ) );
					}
					
					function _PlaceResult( items, imgWidthTarged )
					{
						var content = "";
						if( Array.isArray( items ) && items.length )
						{
							content += cryptocurrency_prices.Ui.TagOpen( "tr" );
						
							var i = 0;
							for( ; i < items.length; i++ )
							{
								var item = items[ i ];
							
								if( i > 0 && ( i % <?php echo( self::ADVERTPRODUCTS_COLS_NUM ); ?> ) == 0 )
									content += cryptocurrency_prices.Ui.TagClose( "tr" ) + cryptocurrency_prices.Ui.TagOpen( "tr" );

								content += cryptocurrency_prices.Ui.Tag( "td", cryptocurrency_prices.Ui.Tag( "div", _GetItemContent( item, imgWidthTarged ), { style: { "margin": "1em 1em 2em 1em" } } ), { "class": "cx-lg-6 cx-xs-12", style: { "vertical-align": "top" } } );
							}

							for( ; i % <?php echo( self::ADVERTPRODUCTS_COLS_NUM ); ?>; i++ )
								content += cryptocurrency_prices.Ui.Tag( "td", null, { "class": "cx-lg-6 cx-xs-12" } );
						
							content += cryptocurrency_prices.Ui.TagClose( "tr" );
							
							content = cryptocurrency_prices.Ui.Tag( "table", cryptocurrency_prices.Ui.Tag( "tbody", content ) );
						}
						
						jQuery( "#<?php echo( $idBlock ); ?> .content" ).html( content );
					}
					
					jQuery.ajax(
						{
							url:		cryptocurrency_prices.Net.UpdateQueryArgs( "<?php echo( $urlRequest ); ?>/v1/advert_products", { slug: "crypto-rates", locale: "<?php echo( Wp::GetLocale() ); ?>" } ),
							type:		"GET",
							dataType:	"json"/*,
							cache:		false*/
						}
					).then(
						function( res ) { _PlaceResult( res, <?php echo( self::ADVERTPRODUCTS_IMG_WIDTH ); ?> ); },
						function( res ) { _PlaceResult( null, 0 ); }
					);
				}
			);

		</script>

		<?php

		$res .= ob_get_clean();

		return( $res );
	}

	static function Sett_SaveBtn( $name )
	{
		return( Ui::Button( Wp::GetLocString( 'Save Changes' ), true, $name ) );
	}

	static function Sett_SaveResultBannerMsg( $hr, $opts = 0, $attrs = null, $errorCtxAlt = null )
	{
		if( $hr == Gen::S_OK || $hr == Gen::S_FALSE )
			return( Ui::BannerMsg( Ui::MsgSucc, _x( 'SaveSuccInfo', 'admin.Common_Settings', 'cryptocurrency-coin-prices' ), $opts, $attrs ) );

		if( Gen::HrSucc( $hr ) )
			return( Ui::BannerMsg( Ui::MsgWarn, sprintf( _x( 'SaveWarnInfo_%1$s', 'admin.Common_Settings', 'cryptocurrency-coin-prices' ), Plugin::GetErrorDescr( $hr, $errorCtxAlt, true ) ), $opts, $attrs ) );

		return( Ui::BannerMsg( Ui::MsgErr, sprintf( _x( 'SaveErrInfo_%1$s', 'admin.Common_Settings', 'cryptocurrency-coin-prices' ), Plugin::GetErrorDescr( $hr, $errorCtxAlt ) ), $opts, $attrs ) );
	}

	static function SettGet()
	{
		return( PluginOptions::Get( PLUGIN_SETT_VER, 'Sett', 'cryptocurrency_prices\\' ) );
	}

	static function SettSet( $data )
	{
		return( PluginOptions::Set( PLUGIN_SETT_VER, 'Sett', $data, 'cryptocurrency_prices\\' ) );
	}

	static function DataGet()
	{
		return( PluginOptions::Get( PLUGIN_DATA_VER, 'Data', 'cryptocurrency_prices\\' ) );
	}

	static function DataSet( $data )
	{
		return( PluginOptions::Set( PLUGIN_DATA_VER, 'Data', $data, 'cryptocurrency_prices\\' ) );
	}

	static function PostSettGet( $postId, $cbType = 'Post' )
	{
		$ver = constant( 'cryptocurrency_prices\\PLUGIN_' . strtoupper( $cbType ) . '_SETT_VER' );
		return( PluginPostOptions::Get( $postId, $ver, 'Sett', $cbType, 'cryptocurrency_prices\\' ) );
	}

	static function PostSettSet( $postId, $data, $cbType = 'Post' )
	{
		$ver = constant( 'cryptocurrency_prices\\PLUGIN_' . strtoupper( $cbType ) . '_SETT_VER' );
		return( PluginPostOptions::Set( $postId, $ver, 'Sett', $data, $cbType, 'cryptocurrency_prices\\' ) );
	}

	static function PostDataGet( $postId, $cbType = 'Post' )
	{
		$ver = constant( 'cryptocurrency_prices\\PLUGIN_' . strtoupper( $cbType ) . '_DATA_VER' );
		return( PluginPostOptions::Get( $postId, $ver, 'Data', $cbType, 'cryptocurrency_prices\\' ) );
	}

	static function PostDataSet( $postId, $data, $cbType = 'Post' )
	{
		$ver = constant( 'cryptocurrency_prices\\PLUGIN_' . strtoupper( $cbType ) . '_DATA_VER' );
		return( PluginPostOptions::Set( $postId, $ver, 'Data', $data, $cbType, 'cryptocurrency_prices\\' ) );
	}

	static function GetRelateRootPath()
	{
		$pluginAbsPath = Gen::ToUnixSlashes( dirname( dirname( __FILE__ ) ) );
		$wpAbsPath = realpath( path_join( $pluginAbsPath, '../../..' ) );
		return( substr( $pluginAbsPath, strlen( $wpAbsPath ) + 1 ) );
	}

	static function GetAbsoluteRootPath( $path = '' )
	{
		$wpAbsPath = Gen::ToUnixSlashes( realpath( path_join( dirname( dirname( __FILE__ ) ), '../../..' ) ) );
		if( !$path )
			return( $wpAbsPath );

		$targetAbsPath = path_join( $wpAbsPath, $path );
		$res = realpath( $targetAbsPath );
		return( $res ? $res : $targetAbsPath );
	}

	static function GetUri( $siteUrlRelative = false )
	{
		return( Plugin::FileUri( 'cryptocurrency-coin-prices', null, $siteUrlRelative ) );
	}

	static function GetApiUri( $funcName = '', $args = array() )
	{
		$res = add_query_arg( array_merge( array( 'cryptocurrency_prices_api' => $funcName ), $args ), 'index.php' );
		if( empty( $funcName ) )
			$res .= '=';
		return( $res );
	}

	static function GetAdminApiUri( $funcName = '', $args = array() )
	{
		$res = add_query_arg( array_merge( array( 'action' => 'cryptocurrency_prices_api', 'fn' => $funcName ), $args ), Net::Url2Uri( get_admin_url( NULL, 'admin.php' ) ) );
		if( empty( $funcName ) )
			$res .= '=';
		return( $res );
	}

	static private $g_bApiCall_Output = true;

	static function ApiCall_EnableOutput( $enable = true )
	{
		$enable = !!$enable;

		if( self::$g_bApiCall_Output == $enable )
			return;

		self::$g_bApiCall_Output = $enable;

		if( $enable )
			ob_end_clean();
		else
			ob_start( function( $data ){ return( '' ); }, 128 );
	}

	static function GetAvailablePlugins( $bActiveOnly = true )
	{
		$res = array();

		$plugins = Plugin::GetAvailablePluginsEx( $bActiveOnly );
		foreach( $plugins as $id => $data )
			$res[] = $id;

		return( $res );
	}

	static function GetAvailablePluginsEx( $bActiveOnly = false )
	{
		$res = array();

		$plugins = get_plugins();
		foreach( $plugins as $id => $data )
		{
			$isActive = is_plugin_active( $id );
			if( $bActiveOnly && !$isActive )
				continue;

			$slug = null;
			$dataFile = null;
			{
				$a = explode( '/', $id );
				if( is_array( $a ) && count( $a ) > 1 )
				{
					$slug = $a[ 0 ];
					$dataFile = $a[ 1 ];
				}
			}

			if( !$slug || !$dataFile )
				continue;

			$data[ 'DataFile' ] = $dataFile;
			$data[ 'IsActive' ] = $isActive;

			$res[ $slug ] = $data;
		}

		return( $res );
	}

	static function _IsSwitchingActive()
	{
		$switchToArg = 'cryptocurrency_prices_switchto';
		return( isset( $_REQUEST[ $switchToArg ] ) );
	}

	static function _RateIt_Set( $mode )
	{
		$state = PluginOptions::Get( self::STATE_VER, 'State', __CLASS__ . '::' );

		if( !$mode )
			$state[ 'rateItRemind' ] = false;
		else
		{
			$rmtCfg = PluginRmtCfg::Get();
			$curTime = intval( time() / 60 );

			$rateItSpanTimeNext = Gen::GetArrField( $rmtCfg, 'Prms.RateItSpanTimeNext', 1440 );
			$state[ 'rateItRemind' ] = $curTime + $rateItSpanTimeNext;
		}

		PluginOptions::Set( self::STATE_VER, 'State', $state, __CLASS__ . '::' );
	}

	static private $_RateIt_ShouldShow = null;

	static function _RateIt_ShouldShow()
	{
		if( self::$_RateIt_ShouldShow !== null )
			return( self::$_RateIt_ShouldShow );
		return( self::$_RateIt_ShouldShow = self::__RateIt_ShouldShow() );
	}

	static private function __RateIt_ShouldShow()
	{
		$state = PluginOptions::Get( self::STATE_VER, 'State', __CLASS__ . '::' );

		$mode = @$state[ 'rateItRemind' ];
		if( $mode === false )
			return( false );

		$rmtCfg = PluginRmtCfg::Get();

		$rateItSpanTime = Gen::GetArrField( $rmtCfg, 'Prms.RateItSpanTime', null );
		if( $rateItSpanTime === null )
			return( false );

		if( $mode !== null )
		{
			$curTime = intval( time() / 60 );
			return( $mode < $curTime ? true : 'postponed' );
		}

		$startUseTime = intval( $state[ 'firstUseTimeStamp' ] / 60 );
		$state[ 'rateItRemind' ] = $startUseTime + $rateItSpanTime;
		PluginOptions::Set( self::STATE_VER, 'State', $state, __CLASS__ . '::' );
		return( 'postponed' );
	}

	static private function _RateIt_Check()
	{
		if( self::_RateIt_ShouldShow() !== true )
		    return( null );

		ob_start();

		?>

		<strong><?php echo( _x( 'PluginTitleFull', 'admin.Common', 'cryptocurrency-coin-prices' ) ); ?></strong>
		<p><?php echo( _x( 'InfoPrefix', 'admin.Common_RateIt', 'cryptocurrency-coin-prices' ) ); ?> <?php echo( self::_GetRateItContent( Plugin::DisplayContent_Block ) ); ?></p>

		<input style="margin-right:1em;vertical-align:middle;" type="button" class="button button-primary" value="<?php echo( _x( 'RateBtn', 'admin.Common_RateIt', 'cryptocurrency-coin-prices' ) ); ?>" onclick="window.open( '<?php echo( self::URL_RATEIT ); ?>', '_blank' );" />

		<span class="actions">
			<input style="margin-right:1em;vertical-align:middle;" type="button" class="button-link" value="<?php echo( _x( 'PostponeBtn', 'admin.Common_RateIt', 'cryptocurrency-coin-prices' ) ); ?>" onclick="cryptocurrency_prices_RateIt_Set( true );" />
			<input style="margin-right:1em;vertical-align:middle;" type="button" class="button-link" value="<?php echo( _x( 'AlreadyRatedBtn', 'admin.Common_RateIt', 'cryptocurrency-coin-prices' ) ); ?>" onclick="cryptocurrency_prices_RateIt_Set( false );" />
		</span>

		<span style="display:none;vertical-align:middle;" class="cryptocurrency_prices_spinner"></span>

		<script>
			function cryptocurrency_prices_RateIt_Set( mode )
			{
				jQuery( "#cryptocurrency_prices_RateIt_Message .actions" ).hide();
				jQuery( "#cryptocurrency_prices_RateIt_Message .cryptocurrency_prices_spinner" ).show();

				jQuery.ajax(
					{
						url: "<?php echo( add_query_arg( array( 'action' => 'cryptocurrency_prices_act', 'fn' => '' ), get_admin_url( NULL, 'admin.php' ) ) ); ?>=" + ( mode ? "rateItPostpone" : "rateItDisable" ),
						type: "post"
					}
				).then(
					function( res )
					{
						jQuery( "#cryptocurrency_prices_RateIt_Message .notice-dismiss" ).click();
					},

					function( res )
					{
						jQuery( "#cryptocurrency_prices_RateIt_Message .actions" ).show();
						jQuery( "#cryptocurrency_prices_RateIt_Message .spinner" ).hide();
					}
				);
			}
		</script>

		<?php

		$res = ob_get_clean();

		return( Ui::BannerMsg( Ui::MsgInfo, $res, Ui::MsgOptDismissible, array( 'id' => 'cryptocurrency_prices_RateIt_Message' ) ) );
	}

	static function _AcceptEula()
	{
		$state = PluginOptions::Get( self::STATE_VER, 'State', __CLASS__ . '::' );
		$state[ 'eulaAcceptedVer' ] = namespace\PLUGIN_EULA_VER;
		PluginOptions::Set( self::STATE_VER, 'State', $state, __CLASS__ . '::' );
	}

	static private function _Eula_Check()
	{
		if( self::IsEulaAccepted() )
			return( NULL );

		$rmtCfg = PluginRmtCfg::Get();

		$acceptBtnName = _x( 'AcceptBtn', 'admin.Common_Eula', 'cryptocurrency-coin-prices' );

		$res = '';
		$res .= '<strong>' . _x( 'PluginTitleFull', 'admin.Common', 'cryptocurrency-coin-prices' ) . '</strong>';

		$res .= '<p>' . vsprintf( _x( 'AcceptInfo_%1$s%2$s%3$s', 'admin.Common_Eula', 'cryptocurrency-coin-prices' ), Gen::ArrFlatten( array(
			Ui::Link( array( '', '' ), Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Links.UrlEula' ), true ),
			$acceptBtnName
		) ) ) . '</p>';

		$res .= '<input type="button" class="button button-primary" value="' . $acceptBtnName . '" style="margin-right: 0.5em;" onclick="window.location.href=\'' . add_query_arg( array( 'action' => 'cryptocurrency_prices_act', 'fn' => 'acceptEula', 'redir' => urlencode( $_SERVER[ 'REQUEST_URI' ] ), '_' => time()  ), get_admin_url( NULL, 'admin.php' ) ) . '\'" />';

		return( Ui::BannerMsg( Ui::MsgWarn, $res, Ui::MsgOptDismissible ) );
	}

	static function _OnFilter_AdminFooterText( $text )
	{
		return( '<span id="footer-thankyou">' . self::_GetRateItContent( Plugin::DisplayContent_Str ) . '</span>' );
	}

	static private function _TransientUpdatePlugins_Process( &$value, $plgVer, $switch )
	{
		if( $plgVer == 'base' )
			return;

		$dwnldUrl = null;

		if( $plgVer == 'ext' )
			$dwnldUrl = Gen::GetArrField( PluginRmtCfg::Get(), 'Links.UrlProductDownload' );

		if( !$dwnldUrl )
			return;

		$pluginKey = Plugin::BASENAME;

		if( $switch && !isset( $value -> response[ $pluginKey ] ) && isset( $value -> no_update[ $pluginKey ] ) )
		{
			$value -> response[ $pluginKey ] = $value -> no_update[ $pluginKey ];
			unset( $value -> no_update[ $pluginKey ] );
		}

		if( isset( $value -> response[ $pluginKey ] ) )
			$value -> response[ $pluginKey ] -> package = $dwnldUrl;
		else if( isset( $value -> no_update[ $pluginKey ] ) )
			$value -> no_update[ $pluginKey ] -> package = $dwnldUrl;
	}

	static private $g_aAlreadyIncludedObj = NULL;

	static function CmnStyle( $ids )
	{
		if( !is_array( $ids ) )
			$ids = array( $ids );

		$filePath = dirname( __FILE__ );

		$res = '';

		foreach( $ids as $id )
		{

			if( @self::$g_aAlreadyIncludedObj[ 'css' ][ $id ] )
				continue;

			$res .= Ui::StyleInline( $filePath . '/' . $id . '.css', '1.0.1', array( 'id' => Plugin::CmnScriptId( $id ) ) );

			self::$g_aAlreadyIncludedObj[ 'css' ][ $id ] = true;
		}

		return( $res );
	}

	static function ScriptId( $id, $prefix = '' )
	{
		if( !is_array( $id ) )
			return( 'cryptocurrency_prices' . ( $prefix ? '_' . $prefix : '' ) . $id );

		$res = array();
		foreach( $id as $idItem )
			$res[] = self::ScriptId( $idItem, $prefix );
		return( $res );
	}

	static function CmnScriptId( $id )
	{
		return( Plugin::ScriptId( $id, 'cmn' ) );
	}

	static function ScriptMinName( $filepath )
	{

		$sepPos = strrpos( $filepath, '.' );
		if( $sepPos === false )
			return( $filepath );

		return( substr( $filepath, 0, $sepPos ) . '.min' . substr( $filepath, $sepPos ) );

	}

	static function CmnScripts( $ids )
	{
		wp_enqueue_script( 'jquery' );

		if( !is_array( $ids ) )
			$ids = array( $ids );

		$fileUrl = Plugin::FileUri( '', __FILE__, true );

		$res = '';

		foreach( $ids as $id )
		{
			if( @self::$g_aAlreadyIncludedObj[ 'js' ][ $id ] )
				continue;

			$deps = array( 'jquery' );

			if( $id == 'Ui' )
			{
				if( is_admin() )
				{
					wp_enqueue_script( 'jquery-ui-sortable' );
					$deps[] = 'jquery-ui-sortable';
				}
			}

			if( $id != 'Cmn' )
				$deps[] = Plugin::CmnScriptId( 'Cmn' );

			wp_enqueue_script( Plugin::CmnScriptId( $id ), add_query_arg( Plugin::GetFileUrlPackageParams(), $fileUrl . '/' . $id . '.js' ), $deps, '1.0.1' );

			self::$g_aAlreadyIncludedObj[ 'js' ][ $id ] = true;
		}

		return( $res );
	}

	static function GetFileUrlPackageParams( $prms = null )
	{
		if( !$prms )
			$prms = array();

		$prms[ 'pk' ] = 'Base';

		return( $prms );
	}

	static function FileUri( $path = null, $plugin = null, $siteUrlRelative = false )
	{
		$path = plugins_url( $path, $plugin );
		return( Net::Url2Uri( $path, $siteUrlRelative ) );
	}

	static function GetLocale()
	{
		return( self::$_locale );
	}

	static function RmtCfgFld_GetCtx( $rmtCfg )
	{
		$locToSiteLang = Gen::GetArrField( $rmtCfg, 'Prms.LocaleToSiteLang' );
		if( !is_array( $locToSiteLang ) )
			$locToSiteLang = array();
		return( array( 'locToSiteLang' => $locToSiteLang ) );
	}

	static function RmtCfgFld_GetLocEx( $ctx, $data, $fieldPath, $sep = '.' )
	{
		$pluginLocale = Plugin::GetLocale();

		$aLocaleSearch = array();
		{
			$aLocaleSearch[] = $pluginLocale;
			if( ( $posSep = strpos( $pluginLocale, '_' ) ) !== false )
				$aLocaleSearch[] = substr( $pluginLocale, 0, $posSep );
			$aLocaleSearch[] = '';
		}

		$v = null;
		foreach( $aLocaleSearch as $localeSearch )
		{
			$v = Gen::GetArrField( $data, $fieldPath . ( $localeSearch ? ( '-' . $localeSearch ) : '' ), null, $sep );
			if( $v !== null )
				break;
		}

		if( !is_string( $v ) )
			return( $v );

		$posSiteLang = strpos( $v, '{SiteLang}' );
		if( $posSiteLang === false )
			return( $v );

		$siteLang = null;
		{
			$locToSiteLang = $ctx[ 'locToSiteLang' ];

			$siteLang = null;
			foreach( $aLocaleSearch as $localeSearch )
			{
				$siteLang = @$locToSiteLang[ $localeSearch ];
				if( $siteLang !== null )
					break;
			}

			if( !is_string( $siteLang ) )
				$siteLang = '';
		}

		$v = substr( $v, 0, $posSiteLang ) . $siteLang . substr( $v, $posSiteLang + strlen( '{SiteLang}' ) );
		return( $v );
	}

	static function RmtCfgFld_GetLoc( $rmtCfg, $fieldPath, $sep = '.' )
	{
		return( Plugin::RmtCfgFld_GetLocEx( Plugin::RmtCfgFld_GetCtx( $rmtCfg ), $rmtCfg, $fieldPath, $sep ) );
	}

	static function GetErrorDescr( $hr, $ctxAlt = null, $tryFindWarnAsError = false )
	{
		$hrLocKey = sprintf( '0x%08X', $hr );
		$hrLocKeyWarnAsErr = $tryFindWarnAsError && Gen::HrSucc( $hr ) ? sprintf( '0x%08X', Gen::HrMake( Gen::SEVERITY_ERROR, Gen::HrFacility( $hr ), $hr ) ) : null;

		$keysToFind = array();

		if( !empty( $ctxAlt ) )
		{
			$keysToFind[] = array( 'id' => $hrLocKey, 'ctx' => $ctxAlt );
			if( $hrLocKeyWarnAsErr )
				$keysToFind[] = array( 'id' => $hrLocKeyWarnAsErr, 'ctx' => $ctxAlt );
		}

		{
			$keysToFind[] = array( 'id' => $hrLocKey, 'ctx' => 'admin.ErrDescr_Common' );
			if( $hrLocKeyWarnAsErr )
				$keysToFind[] = array( 'id' => $hrLocKeyWarnAsErr, 'ctx' => 'admin.ErrDescr_Common' );

			$keysToFind[] = array( 'id' => 'Def_%08X', 'ctx' => 'admin.ErrDescr_Common' );
		}

		foreach( $keysToFind as $keyToFind )
		{
			$id = $keyToFind[ 'id' ];

			$errDescr = sprintf( _x( $id, $keyToFind[ 'ctx' ], 'cryptocurrency-coin-prices' ), $hr );
			if( $errDescr != $id )
				return( $errDescr );
		}

		return( $hrLocKey );

		_x( '0x80004001',		'admin.ErrDescr_Common', 'cryptocurrency-coin-prices' );
		_x( '0x80070005',		'admin.ErrDescr_Common', 'cryptocurrency-coin-prices' );
		_x( 'Def_%08X', 'admin.ErrDescr_Common', 'cryptocurrency-coin-prices' );
	}

	static function _on_parse_request( $continue )
	{
		$apiFunc = @$_REQUEST[ 'cryptocurrency_prices_api' ];
		if( empty( $apiFunc ) )
			return( $continue );

		unset( $_REQUEST[ 'cryptocurrency_prices_api' ] );

		self::_ApiCall_Make( 'cryptocurrency_prices\\OnApi_' . $apiFunc, $_REQUEST );
		exit();

		return( false );
	}

	static function _on_admin_action_act()
	{
		$fn = @$_REQUEST[ 'fn' ];
		if( empty( $fn ) )
		{
			wp_die( '', 400 );
			return;
		}

		unset( $_REQUEST[ 'action' ] );
		unset( $_REQUEST[ 'fn' ] );

		$processed = true;
		switch( $fn )
		{
		case 'acceptEula':
			self::_AcceptEula();

			break;

		case 'rateItDisable':
			self::_RateIt_Set( false );
			break;

		case 'rateItPostpone':
			self::_RateIt_Set( true );
			break;

		default:
			$processed = false;
		}

		if( !$processed )
		{
			wp_die( '', 404 );
			return;
		}

		$redir = @$_REQUEST[ 'redir' ];
		if( !empty( $redir ) )
			wp_redirect( $redir );

		exit();
	}

	static private function _ApiCall_Make( $userFuncId, $args )
	{
		if( !Gen::DoesFuncExist( $userFuncId ) )
		{
			wp_die( '', 404 );
			return;
		}

		foreach( $args as $argKey => $arg )
			$args[ $argKey ] = stripslashes( $arg );

		self::ApiCall_EnableOutput( false );
		$res = call_user_func( $userFuncId, $args );
		self::ApiCall_EnableOutput( true );

		if( $res === null )
			return;

		echo( wp_json_encode( $res ) );
	}

	static function _on_check_plugins_updates( $locales )
	{
		$hrUpdated = PluginRmtCfg::Update();

		return( $locales );
	}

	static function _on_site_transient_update_plugins( $value, $transient )
	{
		if( empty( $value -> checked ) )
			return( $value );

		$plgVer = NULL;
		$switch = false;

		$switchTo = @$_REQUEST[ 'cryptocurrency_prices_switchto' ];
		if( !empty( $switchTo ) )
		{
			$plgVer = $switchTo;
			$switch = true;
		}
		else
			$plgVer = 'base';

		self::_TransientUpdatePlugins_Process( $value, $plgVer, $switch );
		return( $value );
	}

	static function _admin_printscriptsstyles()
	{

		echo( Plugin::CmnScripts( array( 'Cmn', 'Gen', 'Ui' ) ) );
	}

	static function _on_admin_notices()
	{
		$opsRes = @$_REQUEST[ 'cryptocurrency_prices_postOpsRes' ];
		if( !empty( $opsRes ) )
			$opsRes = @json_decode( base64_decode( stripslashes( $opsRes ) ), true );

		{
			$htmlCont = self::_Eula_Check();
			if( $htmlCont )
			{
				self::_admin_printscriptsstyles();
				echo( $htmlCont );

				return;
			}
		}

		{
			$htmlCont = self::_RateIt_Check();
			if( $htmlCont )
			{
				self::_admin_printscriptsstyles();
				echo( $htmlCont );
			}
		}

		if( !empty( $opsRes ) )
			do_action( 'cryptocurrency_prices_postOpsRes', $opsRes );
	}

	static function OnOptRead_State( $state, $verFrom )
	{
		if( $verFrom === null )
		{
			$dataEula = PluginOptions::Get( namespace\PLUGIN_EULA_VER, 'Eula', __CLASS__ . '::' );
			$state[ 'eulaAcceptedVer' ] = $dataEula[ 'acceptedVer' ];
			$state[ '_eulaClearPrevStorage' ] = true;
		}

		return( $state );
	}

	static function OnOptWrite_State( $state )
	{
		if( @$state[ '_eulaClearPrevStorage' ] )
		{
			PluginOptions::Del( 'Eula' );
			unset( $state[ '_eulaClearPrevStorage' ] );
		}

		return( $state );
	}

	static function OnOptRead_Eula( $data, $verFrom )
	{
		$data[ 'acceptedVer' ] = $verFrom;
		return( $data );
	}

	static function _OnPopup_QuestionnaireDeactivate( $id, $prms )
	{
		$rmtCfg = PluginRmtCfg::Get();
		$rmtCfgFldCtx = Plugin::RmtCfgFld_GetCtx( $rmtCfg );

		$q = $prms[ 'q' ];
		$qParams = $q[ 'params' ];

		Ui::PostBoxes_Popup( $id, Plugin::RmtCfgFld_GetLocEx( $rmtCfgFldCtx, $qParams, 'Title' ),
			function( $callbacks_args, $box )
			{
				extract( $box[ 'args' ] );

				echo( Ui::Tag( 'p', Plugin::RmtCfgFld_GetLocEx( $rmtCfgFldCtx, $qParams, 'Description' ) ) );

				echo( Ui::SettBlock_ItemSubTbl_Begin( array( 'class' => 'std questionnaire_body' ) ) );
				{
					$groupName = 'cryptocurrency_prices_questionId';
					$qList = Gen::GetArrField( $qParams, 'Items', array() );
					$userDataMaxSymbolsGlobal = Plugin::RmtCfgFld_GetLocEx( $rmtCfgFldCtx, $rmtCfg, 'Questionnaires.UserDataMaxSymbols' );

					foreach( $qList as $itemId => $qListItem )
					{
						$commentText = Plugin::RmtCfgFld_GetLocEx( $rmtCfgFldCtx, $qListItem, 'Content' );
						$userData = Plugin::RmtCfgFld_GetLocEx( $rmtCfgFldCtx, $qListItem, 'UserData' );
						$isUerDataMultiline = @$qListItem[ 'IsUserDataMultiline' ];

						echo( Ui::TagOpen( 'tr', array( 'class' => 'item' ) ) . Ui::TagOpen( 'td' ) );

						$subContent = '';
						{
							if( !empty( $commentText ) )
								$subContent .= Ui::Tag( 'div', $commentText, array( 'class' => 'item description' ) );

							if( !empty( $userData ) )
							{
								$attrs = array( 'placeholder' => $userData, 'class' => 'item userdata', 'style' => array( 'width' => '100%' ) );
								if( !empty( $userDataMaxSymbolsGlobal ) )
									$attrs[ 'maxlength' ] = $userDataMaxSymbolsGlobal;

								if( $isUerDataMultiline )
								{
									$attrs[ 'style' ][ 'min-height' ] = 2 * (3/2) . 'em';
									$attrs[ 'style' ][ 'max-height' ] = 5 * (3/2) . 'em';
									$attrs[ 'type' ][ 'max-height' ] = 'text';
									$subContent .= Ui::Tag( 'textarea', null, $attrs );
								}
								else
									$subContent .= Ui::TextBox( null, null, $attrs );
							}
						}

						echo( Ui::RadioBox( Plugin::RmtCfgFld_GetLocEx( $rmtCfgFldCtx, $qListItem, 'Label' ), $groupName, $itemId ) );
						if( !empty( $subContent ) )
							echo( Ui::Tag( 'div', $subContent, array( 'class' => 'subblock', 'style' => array(  ) ) ) );

						echo( Ui::TagClose( 'td' ) . Ui::TagClose( 'tr' ) );
					}
				}
				echo( Ui::SettBlock_ItemSubTbl_End() );

				echo( Ui::TagOpen( 'span' ) );
				{
					echo( Ui::Button( Wp::GetLocString( 'Deactivate' ), true, null, 'ctlSpaceAfter ctlVaMiddle actOk', 'button', array( 'disabled' => '', 'style' => array( 'min-width' => '7em' ), 'onclick' => '' ) ) );
					echo( Ui::Button( Wp::GetLocString( 'Cancel' ), false, null, 'ctlSpaceAfter ctlVaMiddle actCancel', 'button', array( 'style' => array( 'min-width' => '7em' ), 'onclick' => '' ) ) );
					echo( Ui::Tag( 'span', null, array( 'class' => 'ctlVaMiddle cryptocurrency_prices_spinner', 'style' => array( 'display' => 'none' ) ) ) );
				}
				echo( Ui::TagClose( 'span' ) );

?>

				<script>
					(function()
					{
						var box = jQuery( "#<?php echo( $box[ 'id' ] ); ?>" );
						var deactivateUrl = "<?php echo( $prms[ 'href' ] ); ?>";
						
						box.find( ".questionnaire_body .item input[type=\'radio\']" ).change(
							function()
							{
								box.find( ".questionnaire_body .item" ).removeClass( "selected" );
								jQuery( this ).closest( ".item" ).addClass( "selected" );

								box.find( ".actOk" ).prop( "disabled", false );
							}
						);
						
						box.find( ".actCancel" ).on( "click",
							function()
							{
								box.find( ".notice-dismiss" ).click();
							}
						);

						box.find( ".actOk" ).on( "click",
							function()
							{
								var selItem = box.find( ".questionnaire_body .item.selected" );
								_ProcessAnswerAndDeactivateAndClose(
									selItem.find( "input[type=\'radio\']" ).val(),
									selItem.find( ".userdata" ).val(),
								);
							}
						);

						function _DeactivateAndClose()
						{
							box.find( ".cryptocurrency_prices_spinner" ).hide();
							box.find( ".notice-dismiss" ).click();
							//


							location = deactivateUrl;
							//
						}

						function _ProcessAnswerAndDeactivateAndClose( answerId, answerUserData )
						{
							if( answerId == '_' )
							{
								_DeactivateAndClose();
								return;
							}

							box.find( ".actOk" ).prop( "disabled", true );
							box.find( ".cryptocurrency_prices_spinner" ).show();

							var sendDataUrl = "<?php echo( Gen::GetArrField( $rmtCfg, 'Questionnaires.SendAnswerUrlTpl' ) ); ?>";
							sendDataUrl = sendDataUrl.replace( "{EndPointId}",					encodeURI( "<?php echo( Gen::GetSiteId() ); ?>" ) );
							sendDataUrl = sendDataUrl.replace( "{PluginVersion}",				encodeURI( "1.0.1" ) );
							sendDataUrl = sendDataUrl.replace( "{PluginMode}",					encodeURI( "base" ) );
							sendDataUrl = sendDataUrl.replace( "{PluginPackage}",				encodeURI( "Base" ) );
							sendDataUrl = sendDataUrl.replace( "{QuestionnaireId}",				encodeURI( "<?php echo( @$q[ 'id' ] ); ?>" ) );
							sendDataUrl = sendDataUrl.replace( "{QuestionnaireVersionId}",		encodeURI( "<?php echo( @$qParams[ 'VersionId' ] ); ?>" ) );
							sendDataUrl = sendDataUrl.replace( "{AnswerId}",					encodeURI( answerId ) );
							sendDataUrl = sendDataUrl.replace( "{AnswerUserData}",				answerUserData ? encodeURI( answerUserData ) : "" );
							
							jQuery.ajax( { url: sendDataUrl, type: "post" } ).then(
								function( res )
								{
									_DeactivateAndClose();
								},

								function( res )
								{
									_DeactivateAndClose();
								},
							);
						}
					})();
				</script>
				
				<?php
			},
			get_defined_vars()
		);
	}
}

