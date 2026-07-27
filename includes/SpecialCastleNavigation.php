<?php
/**
 * Special:CastleNavigation — see every room's exits, and which ones are broken.
 *
 * Read-only for now (Stage 1). The point of this stage is to get the layout in front of
 * a human before committing to a storage backend, so nothing here writes: the views render
 * from the same map the renderer uses, and the editing controls are shown disabled.
 */

namespace MediaWiki\Extension\NavigationForPagesAsRooms;

use MediaWiki\Category\Category;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\Widget\TitleInputWidget;
use NavigationForPagesAsRooms;

class SpecialCastleNavigation extends SpecialPage {

	public function __construct() {
		// Read-only diagnostics over already-public content, so no restriction yet.
		// The 'editinterface' gate lands with the write path in Stage 2.
		parent::__construct( 'CastleNavigation' );
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'wiki';
	}

	/** @inheritDoc */
	public function execute( $subPage ) {
		$this->setHeaders();
		$this->outputHeader();

		$rooms = NavigationForPagesAsRooms::getRooms();
		$subPage = $subPage !== null ? trim( $subPage ) : '';

		if ( $subPage !== '' ) {
			$this->showRoom( $rooms, strtolower( $subPage ) );
		} else {
			$this->showIndex( $rooms );
		}
	}

	/**
	 * Resolve "does this page exist" for many titles at once.
	 *
	 * The index needs existence for every room and every destination — several hundred
	 * lookups. LinkBatch resolves them in one query and primes the title cache, so the
	 * per-title checks afterwards are free.
	 *
	 * @param string[] $titleTexts
	 * @return array<string,bool> original text => exists
	 */
	private function resolveExistence( array $titleTexts ): array {
		$linkBatch = MediaWikiServices::getInstance()
			->getLinkBatchFactory()
			->newLinkBatch();

		$titles = [];
		foreach ( array_unique( $titleTexts ) as $text ) {
			$title = Title::newFromText( $text );
			if ( $title ) {
				$titles[$text] = $title;
				$linkBatch->addObj( $title );
			}
		}
		$linkBatch->execute();

		$exists = [];
		foreach ( $titleTexts as $text ) {
			// A title that would not parse can't exist; report it rather than skipping it.
			$exists[$text] = isset( $titles[$text] ) && $titles[$text]->exists();
		}
		return $exists;
	}

	/**
	 * @param array<string,string> $rooms
	 */
	private function showIndex( array $rooms ): void {
		$out = $this->getOutput();

		// Gather every title the index will ask about — rooms and destinations alike —
		// so the whole page costs one existence query.
		$wanted = [];
		$parsedRooms = [];
		foreach ( $rooms as $key => $wikitext ) {
			$targets = NavigationSlots::targets( $wikitext );
			$parsedRooms[$key] = $targets;
			$wanted[] = $key;
			foreach ( $targets as $target ) {
				$wanted[] = $target;
			}
		}
		$exists = $this->resolveExistence( $wanted );

		$rows = [];
		$brokenRooms = 0;
		$brokenTargets = 0;
		foreach ( $parsedRooms as $key => $targets ) {
			$missing = array_values( array_filter(
				$targets,
				static fn ( string $t ) => empty( $exists[$t] )
			) );
			if ( $missing ) {
				$brokenRooms++;
				$brokenTargets += count( $missing );
			}
			$rows[] = [
				'key' => $key,
				'pageExists' => !empty( $exists[$key] ),
				'exits' => count( $targets ),
				'missing' => $missing,
			];
		}

		$out->addHTML( $this->summaryHtml( count( $rows ), $brokenRooms, $brokenTargets ) );
		$out->addHTML( $this->indexTableHtml( $rows ) );
		$out->addHTML( $this->noEntryHtml() );
	}

	/**
	 * Pages that render <navigation/> but have no map entry.
	 *
	 * The table above can only report rooms the map already knows about. This is the
	 * other direction — the drift that used to be invisible. There is no search backend
	 * here to scan wikitext for the tag, so the renderer tags these pages into a tracking
	 * category as they parse, and this lists that category.
	 */
	private function noEntryHtml(): string {
		$html = Html::element( 'h2', [],
			$this->msg( 'castlenavigation-noentry-heading' )->text() );

		$categoryName = $this->msg( 'nfpar-tracking-category-no-entry' )->inContentLanguage()->text();
		$category = Category::newFromName( strtr( $categoryName, ' ', '_' ) );

		$members = $category ? $category->getMembers() : null;
		$items = [];
		if ( $members ) {
			foreach ( $members as $title ) {
				$items[] = Html::rawElement( 'li', [],
					$this->getLinkRenderer()->makeLink( $title ) );
			}
		}

		if ( !$items ) {
			// Deliberately not phrased as "everything is fine": the category only fills
			// as pages are parsed, so an empty list means "nothing seen yet", not "none".
			return $html . Html::element( 'p', [],
				$this->msg( 'castlenavigation-noentry-empty' )->text() );
		}

		return $html . Html::rawElement( 'ul', [], implode( '', $items ) );
	}

	private function summaryHtml( int $roomCount, int $brokenRooms, int $brokenTargets ): string {
		$msg = $this->msg( 'castlenavigation-summary' )
			->numParams( $roomCount, $brokenRooms, $brokenTargets )
			->escaped();
		return Html::rawElement( 'p', [ 'class' => 'nfpar-summary' ], $msg );
	}

	/**
	 * @param array<int,array{key:string,pageExists:bool,exits:int,missing:string[]}> $rows
	 */
	private function indexTableHtml( array $rows ): string {
		$html = Html::openElement( 'table', [
			// 'sortable' gives column sorting for free; 'nfpar-index' is our hook for
			// "show only broken rooms" filtering.
			'class' => 'wikitable sortable nfpar-index',
		] );

		$html .= Html::openElement( 'tr' );
		foreach ( [ 'room', 'page', 'exits', 'broken', 'status' ] as $col ) {
			$html .= Html::element( 'th', [], $this->msg( "castlenavigation-col-$col" )->text() );
		}
		$html .= Html::closeElement( 'tr' );

		foreach ( $rows as $row ) {
			$html .= $this->indexRowHtml( $row );
		}

		return $html . Html::closeElement( 'table' );
	}

	/**
	 * @param array{key:string,pageExists:bool,exits:int,missing:string[]} $row
	 */
	private function indexRowHtml( array $row ): string {
		$isBroken = $row['missing'] || !$row['pageExists'];

		if ( !$row['pageExists'] ) {
			$status = $this->msg( 'castlenavigation-status-nopage' )->text();
		} elseif ( $row['missing'] ) {
			$status = $this->msg( 'castlenavigation-status-brokentargets' )
				->params( $this->getLanguage()->commaList( $row['missing'] ) )
				->text();
		} else {
			$status = $this->msg( 'castlenavigation-status-ok' )->text();
		}

		$detail = $this->getPageTitle( $row['key'] );

		$cells = Html::rawElement( 'td', [],
			$this->getLinkRenderer()->makeLink( $detail, $row['key'] ) );
		$cells .= Html::element( 'td', [], $row['pageExists'] ? '✓' : '✗' );
		$cells .= Html::element( 'td', [], (string)$row['exits'] );
		$cells .= Html::element( 'td', [], (string)count( $row['missing'] ) );
		$cells .= Html::element( 'td', [], $status );

		return Html::rawElement( 'tr',
			$isBroken ? [ 'class' => 'nfpar-broken' ] : [],
			$cells );
	}

	/**
	 * @param array<string,string> $rooms
	 */
	private function showRoom( array $rooms, string $key ): void {
		$out = $this->getOutput();

		if ( !isset( $rooms[$key] ) ) {
			$out->addHTML( Html::element( 'p', [ 'class' => 'error' ],
				$this->msg( 'castlenavigation-noroom', $key )->text() ) );
			$out->addHTML( Html::rawElement( 'p', [],
				$this->getLinkRenderer()->makeLink(
					$this->getPageTitle(),
					$this->msg( 'castlenavigation-backtoindex' )->text() ) ) );
			return;
		}

		$out->addHTML( $this->roomDetailHtml( $key, $rooms[$key] ) );
	}

	private function roomDetailHtml( string $key, string $wikitext ): string {
		$parsed = NavigationSlots::parse( $wikitext );
		$exists = $this->resolveExistence( NavigationSlots::targets( $wikitext ) );

		// The destination fields are real TitleInputWidgets, just disabled — so what gets
		// judged here is the layout Stage 2 will actually ship, not an approximation.
		$out = $this->getOutput();
		$out->enableOOUI();
		$out->addModules( [ 'mediawiki.widgets' ] );

		$html = Html::element( 'h2', [], $key );

		$html .= Html::element( 'h3', [],
			$this->msg( 'castlenavigation-prose-heading' )->text() );
		$html .= Html::element( 'textarea', [
			'class' => 'nfpar-prose',
			'rows' => 4,
			'readonly' => true,
			'disabled' => true,
		], $parsed['prose'] );

		$html .= Html::element( 'h3', [],
			$this->msg( 'castlenavigation-destinations-heading' )->text() );
		$html .= $this->slotsTableHtml( $parsed['slots'], $exists );

		$html .= Html::rawElement( 'p', [],
			$this->getLinkRenderer()->makeLink(
				$this->getPageTitle(),
				$this->msg( 'castlenavigation-backtoindex' )->text() ) );

		return $html;
	}

	/**
	 * @param array<int,array{target:string,label:?string}> $slots
	 * @param array<string,bool> $exists
	 */
	private function slotsTableHtml( array $slots, array $exists ): string {
		if ( !$slots ) {
			return Html::element( 'p', [],
				$this->msg( 'castlenavigation-noexits' )->text() );
		}

		$html = Html::openElement( 'table', [ 'class' => 'wikitable nfpar-slots' ] );
		$html .= Html::openElement( 'tr' );
		foreach ( [ 'slot', 'destination', 'shownas', 'page' ] as $col ) {
			$html .= Html::element( 'th', [], $this->msg( "castlenavigation-col-$col" )->text() );
		}
		$html .= Html::closeElement( 'tr' );

		foreach ( $slots as $n => $slot ) {
			$ok = !empty( $exists[$slot['target']] );

			// Disabled in Stage 1, but otherwise the widget Stage 2 will use: an
			// autocompleting title input. Stage 2 wraps it in HTMLTitleTextField with
			// 'exists' => true, which refuses to save a destination that isn't a real page.
			$input = new TitleInputWidget( [
				'value' => $slot['target'],
				'disabled' => true,
				'classes' => [ 'nfpar-destination' ],
			] );

			$cells = Html::element( 'td', [], '{' . $n . '}' );
			$cells .= Html::rawElement( 'td', [], (string)$input );
			$cells .= Html::element( 'td', [], $slot['label'] ?? '' );
			$cells .= Html::element( 'td', [], $ok ? '✓' : '✗' );

			$html .= Html::rawElement( 'tr',
				$ok ? [] : [ 'class' => 'nfpar-broken' ],
				$cells );
		}

		return $html . Html::closeElement( 'table' );
	}
}
