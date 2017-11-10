# FacetWP - P2P

## Before updating

This version is **not compatible** with P2P facets created with previous version of this plugin. You will need to recreate and re-run the indexing process.

## Description

This plugin add two new type of sources for your facets, Posts to Posts (P2P) connections and their metas.

## Installation

### Requirements

* FacetWP 2.0.4 or greater

### Installation

1. Unpack the download-package.
2. Upload the files to the /wp-content/plugins/ directory.
3. Activate the plugin

## Changelog

### 2.1.0 (Nov 10, 2017)

* New filter `facetp2p_p2p_index_params` : Change the data generated for a post when indexing a P2P connexion
* New filter `facetp2p_p2pmeta_index_params` : Change the data generated for post when indexing a P2P metas
* Fix some coding standards

### 2.0.0 (Oct 19, 2017)

Complete rewrite of the plugin. Now directly hook into FacetWP sources list instead of adding a custom facet.

* Add support for P2P connections in FacetWP sources
* Add support for P2P connection metas in FacetWP sources
