<?php
/**
 * Collection MediaWiki extension.
 *
 * This extension implements a <collection> tag creating a gallery of all images in
 * a category.
 *
 * Written by Børre Gaup <borre.gaup@uit.no>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Extensions
 */
if( !defined( 'MEDIAWIKI' ) ) {
	echo( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
	die( 1 );
}

$wgExtensionCredits['parserhook'][] = array(
	'path' => __FILE__,
	'name' => 'Collection',
	'author' => 'Børre Gaup',
	'description' => 'Adds <nowiki><collection></nowiki> tag',
	'version' => '0.1.0'
);

$wgExtensionFunctions[] = "Collection::collectionSetHook";

class Collection {
	public static function collectionSetHook() {
		global $wgParser;
		$wgParser->setHook( "collection",
			"Collection::renderCollection" );
	}

	function onParserSetup( Parser $parser ) {
		// When the parser sees the <collection> tag, it executes renderCollection (see below)
		$parser->setHook( 'collection', 'Collection::renderCollection' );
	}

	function startsWith($haystack, $needle) {
		// search backwards starting from haystack length characters from the end
		return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
	}

	public static function renderCollection( $input, array $args, Parser $parser, PPFrame $frame ) {
		$string_array = array();

		$parsed_input = $parser->recursiveTagParse( $input, $frame );

		if ( Collection::startsWith( $parsed_input, "Coll" ) === true ) {
			$results = Collection::getResults( $parsed_input );

			$string_array[] = '{| class="wikitable"';
			$string_array[] = "! ";
			foreach ( $results['langs'] as $lang ) {
				$string_array[] = "!" . $lang;
			}
			$string_array[] = "|-";
			foreach ( array_keys( $results['data'] ) as $pagename ) {
				$string_array[] = "|[[" . $pagename . " |Edit]]";
				foreach ( $results['langs'] as $lang ) {
					if ( isset($results['data'][$pagename][$lang]) ) {
						$string_array[] = "|" . implode( "<br/>", $results['data'][$pagename][$lang] );
					} else {
						$string_array[] = "|";
					}
				}
				$string_array[] = "|-";
			}

			$string_array[] = "|}";
			return $parser->recursiveTagParse( implode("\n\n", $string_array), $frame );
		} else {
			return $parser->recursiveTagParse( "No results", $frame );
		}
	}

	protected function getResults( $input ) {
		$query = "[[Collection::" . $input . "]]|?Expression|?Language";

		$rawParams = explode( '|', $query );

		list( $queryString, $parameters, $printouts ) = SMWQueryProcessor::getComponentsFromFunctionParams( $rawParams, false );

		SMWQueryProcessor::addThisPrintout( $printouts, $parameters );

		$smwQuery = SMWQueryProcessor::createQuery(
			$queryString,
			SMWQueryProcessor::getProcessedParams( $parameters, $printouts ),
			SMWQueryProcessor::SPECIAL_PAGE,
			'',
			$printouts
		);

		$smwQuery->setUnboundLimit(50000);
		$smwQueryResult = smwfGetStore()->getQueryResult( $smwQuery );

		$results = $smwQueryResult->toArray()['results'];

		$modified_results = array();
		foreach ( array_keys( $results ) as $key ) {
			$short_key = explode( "#", $key )[0];
			$lang = $results[$key]['printouts']['Language'][0];
			$langs[$lang] = null;
			$expression = explode( ":", $results[$key]['printouts']['Expression'][0]['fulltext'] )[1];
			$modified_results[$short_key][$lang][] = $expression;
		}

		return array('data' => $modified_results, 'langs' => array_keys( $langs ) );
	}
}
