<?php

namespace cryptocurrency_prices;

if( !defined( 'ABSPATH' ) )
	exit;

include( 'common.php' );

Plugin::Init();

add_action( 'widgets_init', 'cryptocurrency_prices\OnWidgetsInit' );
add_shortcode( 'cryptocurrency_prices', 'cryptocurrency_prices\OnShortcode' );

function OnActivate()
{
}

function OnDeactivate()
{
}

function OnInitAdminMode()
{
}

function OnWidgetsInit()
{
	register_widget( 'cryptocurrency_prices\MainWidget' );
}

$ShortcodeId = 0;

function OnShortcode( $sett )
{
	_LoadScripts( true );

	global $ShortcodeId;

	$ShortcodeId++;

	$id = 'cryptocurrency_prices_shortcode_content-' . $ShortcodeId;

	$res = '';
	$res .= '<div id="' . $id . '" class="cryptocurrency_prices">';
	$res .= GetContent( $sett, $id, 'cryptocurrency_prices' );
	$res .= '</div>';

	return( $res );
}

function GetContent( $sett, $id, $class )
{
	ob_start();

	?>

	<style>
		.<?php echo( $class ); ?> .percent_change_24h.inc{color:#009933;}
		.<?php echo( $class ); ?> .percent_change_24h.dec{color:#B50000;}
	</style>
	
	<div class="content"></div>
	<script>
		jQuery( document ).on( 'ready',
			function( $ )
			{
				if( !cryptocurrency_prices.App._int.apiUri )
					cryptocurrency_prices.App._int.apiUri = "<?php echo( Plugin::GetApiUri() ); ?>";
				
				var ctlContent = jQuery( "<?php echo( '#' . $id ); ?> .content" );

				function _RefreshContent()
				{
					cryptocurrency_prices.App.GetContent(
						"<?php echo( Gen::GetArrField( $sett, 'dataProvider', 'coinMarketCap', '.', true ) ); ?>",
						{
							itemsMax:		<?php echo( Gen::GetArrField( $sett, 'itemsMax', 10, '.', true ) ); ?>,
							fontScale:		<?php echo( Gen::GetArrField( $sett, 'fontScale', 100, '.', true ) / 100 ); ?>,
							showCoinsLinks:	<?php echo( Gen::GetArrField( $sett, 'showCoinsLinks', true, '.', true ) ? 'true' : 'false' ); ?>,
							coinsLinksTpl:	"<?php echo( Gen::GetArrField( $sett, 'coinsLinksTpl', '/coins/{symbol}/{slug}/', '.', true ) ); ?>"
						}
					).then(
						function( res )
						{
							if( res )
								ctlContent.html( res );

							ctlContent.find( ".sparkline-charts" ).each(
								function( index )
								{
									cryptocurrency_prices.App.GenerateSmallChart( this, { isChartFill: true, pointsNum: 0, currencyPrice: 1, currency: "USD", currencySymbol: "$" } );
								}
							);
						}
					);
				}

				_RefreshContent();
				setInterval( function() { _RefreshContent(); }, <?php echo( Gen::GetArrField( $sett, 'updatePeriod', 60, '.', true ) ); ?> * 1000 );
			}
		);
	</script>
	
	<?php

	return( ob_get_clean() );
}

$WidgetId = 0;

class MainWidget extends \WP_Widget
{
	function __construct()
	{
		parent::__construct(
			'cryptocurrency_prices_main_widget', esc_html( Plugin::GetPluginString( 'Title' ) ),
			array( 'description' => esc_html( Plugin::GetPluginString( 'Description' ) ) )
		);
	}

	public function widget( $args, $sett )
	{
		global $WidgetId;

		$WidgetId++;

		$id = 'cryptocurrency_prices_widget_content-' . $WidgetId;

		_LoadScripts( true );

		echo( $args[ 'before_widget' ] );

		if( !empty( $sett[ 'title' ] ) )
			echo( $args[ 'before_title' ] . apply_filters( 'widget_title', $sett[ 'title' ] ) . $args[ 'after_title' ] );

		echo( '<div id="' . $id . '" class="cryptocurrency_prices">' );
		echo( GetContent( $sett, $id, 'cryptocurrency_prices' ) );
		echo( '</div>' );

		echo( $args[ 'after_widget' ] );
	}

	public function form( $sett )
	{
		_LoadScripts( false );

		?>

		<p>
			<?php $id = 'title'; ?>
			<label for="<?php echo( esc_attr( $this -> get_field_id( $id ) ) ); ?>">
				<?php echo( esc_attr( _x( 'TitleLabel' , 'admin.Widget', 'cryptocurrency-coin-prices' ) ) ); ?>
			</label>
			<?php echo( Ui::TextBox( $this -> get_field_id( $id ), Gen::GetArrField( $sett, $id, _x( 'TitleDef' , 'admin.Widget', 'cryptocurrency-coin-prices' ) ), array( 'class' => 'widefat', 'name' => $this -> get_field_name( $id ) ) ) ); ?>
		</p>

		<p>
			<?php $id = 'itemsMax'; ?>
			<label for="<?php echo( esc_attr( $this -> get_field_id( $id ) ) ); ?>">
				<?php echo( esc_attr( _x( 'MaxItemsLabel' , 'admin.Widget', 'cryptocurrency-coin-prices' ) ) ); ?>
			</label>
			<?php echo( Ui::NumberBox( $this -> get_field_id( $id ), Gen::GetArrField( $sett, $id, 10 ), array( 'class' => 'widefat', 'name' => $this -> get_field_name( $id ), 'min' => 3 ) ) ); ?>
		</p>

		<p>
			<?php $id = 'updatePeriod'; ?>
			<label for="<?php echo( esc_attr( $this -> get_field_id( $id ) ) ); ?>">
				<?php echo( esc_attr( _x( 'AutoUpdateIntervalsLabel' , 'admin.Widget', 'cryptocurrency-coin-prices' ) ) ); ?>
			</label>
			<?php echo( Ui::NumberBox( $this -> get_field_id( $id ), Gen::GetArrField( $sett, $id, 60 ), array( 'class' => 'widefat', 'name' => $this -> get_field_name( $id ), 'min' => 1 ) ) ); ?>
		</p>

		<p>
			<?php $id = 'fontScale'; ?>
			<label for="<?php echo( esc_attr( $this -> get_field_id( $id ) ) ); ?>">
				<?php echo( esc_attr( _x( 'FontScaleLabel' , 'admin.Widget', 'cryptocurrency-coin-prices' ) ) ); ?>
			</label>
			<?php echo( Ui::NumberBox( $this -> get_field_id( $id ), Gen::GetArrField( $sett, $id, 100 ), array( 'class' => 'widefat', 'name' => $this -> get_field_name( $id ), 'min' => 1 ) ) ); ?>
		</p>

		<p>
			<?php $id = 'dataProvider'; ?>
			<label for="<?php echo( esc_attr( $this -> get_field_id( $id ) ) ); ?>">
				<?php echo( esc_attr( _x( 'DataProviderLabel' , 'admin.Widget', 'cryptocurrency-coin-prices' ) ) ); ?>
			</label>
			<?php
			$items = array();
			$items[ 'coinMarketCap' ] = 'coinmarketcap.com';

			$items[ 'coinCapIo' ] = 'coincap.io';

			echo( Ui::ComboBox( $this -> get_field_id( $id ), $items, Gen::GetArrField( $sett, $id, 'coinMarketCap' ), false, array( 'class' => 'widefat', 'name' => $this -> get_field_name( $id ) ) ) );
			?>
		</p>

		<p>
			<?php $id = 'coinsLinksTpl'; ?>
			<label for="<?php echo( esc_attr( $this -> get_field_id( $id ) ) ); ?>">
				<?php echo( esc_attr( _x( 'CoinsLinksTplLabel' , 'admin.Widget', 'cryptocurrency-coin-prices' ) ) ); ?>
			</label>
			<?php echo( Ui::TextBox( $this -> get_field_id( $id ), Gen::GetArrField( $sett, $id, '/coins/{symbol}/{slug}/' ), array( 'class' => 'widefat', 'name' => $this -> get_field_name( $id ) ) ) ); ?>
		</p>

		<p>
			<?php $id = 'showCoinsLinks'; ?>
			<?php echo( Ui::CheckBox( esc_attr( _x( 'ShowCoinsLinksChk' , 'admin.Widget', 'cryptocurrency-coin-prices' ) ), $this -> get_field_id( $id ), Gen::GetArrField( $sett, $id, true ), false, null, null, array( 'name' => $this -> get_field_name( $id ) ) ) ); ?>
		</p>

		<?php
	}

	public function update( $sett, $settPrev )
	{
		$sett[ 'title' ] = ( !empty( $sett[ 'title' ] ) ) ? sanitize_text_field( $sett[ 'title' ] ) : '';
		$sett[ 'itemsMax' ] = intval( $sett[ 'itemsMax' ] );
		$sett[ 'updatePeriod' ] = intval( $sett[ 'updatePeriod' ] );
		$sett[ 'fontScale' ] = intval( $sett[ 'fontScale' ] );
		$sett[ 'dataProvider' ] = $sett[ 'dataProvider' ];
		$sett[ 'coinsLinksTpl' ] = ( !empty( $sett[ 'coinsLinksTpl' ] ) ) ? sanitize_text_field( $sett[ 'coinsLinksTpl' ] ) : '';
		$sett[ 'showCoinsLinks' ] = isset( $sett[ 'showCoinsLinks' ] );
		return( $sett );
	}
}

