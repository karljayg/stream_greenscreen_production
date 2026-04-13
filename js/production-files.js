/**
 * Production media root: local (relative production_files/) or remote base URL.
 * Classic script, loaded before stream_production.js so inline code can resolve paths.
 */
(function (window) {
	'use strict';

	var DEFAULT_REMOTE = 'https://psistorm.com/stream_production/production_files';

	var state = {
		mode: 'local',
		remoteBaseUrl: DEFAULT_REMOTE
	};

	function stripTrailingSlashes(s) {
		return String(s).replace(/\/+$/, '');
	}

	function withTrailingSlash(s) {
		var t = stripTrailingSlashes(s);
		return t ? t + '/' : '';
	}

	function streamProductionAppRootFromRemoteBase(remoteUrl) {
		var rb = stripTrailingSlashes(String(remoteUrl || ''));
		if (!rb || !/\/production_files$/i.test(rb)) return '';
		return rb.replace(/\/production_files$/i, '') + '/';
	}

	/** Keep mood music + 2026 overlay base in sync when toggling local/remote (page load uses PHP; Apply must update globals). */
	function syncStreamGlobalsWithProductionMode() {
		var musicLocked = window.MX_MUSIC_PATH_LOCKED === true;
		var sceneLocked = window.STREAM_SCENE_ASSETS_BASE_LOCKED === true;
		if (state.mode !== 'remote') {
			if (!musicLocked) window.MX_MUSIC_PATH = 'music/';
			if (!sceneLocked && typeof window.STREAM_SCENE_ASSETS_BASE !== 'undefined') window.STREAM_SCENE_ASSETS_BASE = '';
			return;
		}
		var root = streamProductionAppRootFromRemoteBase(state.remoteBaseUrl);
		if (!root) return;
		if (!musicLocked) window.MX_MUSIC_PATH = root + 'music/';
		if (!sceneLocked && typeof window.STREAM_SCENE_ASSETS_BASE !== 'undefined') window.STREAM_SCENE_ASSETS_BASE = root;
	}

	function applyProductionFilesSettings(cfg) {
		if (!cfg || typeof cfg !== 'object') return;
		state.mode = cfg.mode === 'remote' ? 'remote' : 'local';
		if (typeof cfg.remoteBaseUrl === 'string' && cfg.remoteBaseUrl.trim()) {
			state.remoteBaseUrl = stripTrailingSlashes(cfg.remoteBaseUrl.trim());
		} else {
			state.remoteBaseUrl = DEFAULT_REMOTE;
		}
		syncStreamGlobalsWithProductionMode();
		refreshProductionFilesDom();
	}

	function getRemoteRoot() {
		return withTrailingSlash(state.remoteBaseUrl || DEFAULT_REMOTE);
	}

	/**
	 * Resolve a path that is normally relative to the app root.
	 * Absolute http(s) URLs unchanged. production_files/... maps under remote root in remote mode.
	 * Other relative paths (e.g. ../) resolve against the remote root via URL().
	 */
	function resolveProductionUrl(path) {
		if (path == null || path === '') return path;
		var p = String(path);
		if (/^https?:\/\//i.test(p) || p.indexOf('//') === 0) return p;
		if (state.mode !== 'remote') return p;
		var base = getRemoteRoot();
		var rel = p.replace(/^\.?\//, '');
		if (rel.indexOf('production_files/') === 0) {
			return base + rel.slice('production_files/'.length);
		}
		try {
			return new URL(p, base).href;
		} catch (e) {
			return base + rel.replace(/^\/+/, '');
		}
	}

	function getAudioBase() {
		return resolveProductionUrl('production_files/audio/');
	}

	function getVideoBase() {
		return resolveProductionUrl('production_files/video/');
	}

	function getImagesBase() {
		return resolveProductionUrl('production_files/images/');
	}

	function refreshProductionFilesDom() {
		var v = window.ASSET_VERSION || '';
		var sep = v ? '?v=' + encodeURIComponent(String(v)) : '';
		var gif = document.getElementById('gif-image');
		if (gif) {
			gif.src = resolveProductionUrl('production_files/images/transparent_greenscreen.gif') + sep;
		}
		var favPath = resolveProductionUrl('production_files/images/favicon.ico');
		var links = document.querySelectorAll('link[rel="icon"], link[rel="shortcut icon"]');
		for (var i = 0; i < links.length; i++) {
			var href = links[i].getAttribute('href') || '';
			if (href.indexOf('favicon') !== -1) links[i].href = favPath + sep;
		}
	}

	window.PRODUCTION_FILES_DEFAULT_REMOTE = DEFAULT_REMOTE;
	window.applyProductionFilesSettings = applyProductionFilesSettings;
	window.resolveProductionUrl = resolveProductionUrl;
	window.getProductionAudioBase = getAudioBase;
	window.getProductionVideoBase = getVideoBase;
	window.getProductionImagesBase = getImagesBase;
	window.refreshProductionFilesDom = refreshProductionFilesDom;

	window.getProductionFilesMode = function () {
		return state.mode === 'remote' ? 'remote' : 'local';
	};

	/**
	 * Set crossOrigin="anonymous" before assigning cross-origin media src so canvas
	 * (chroma key) and Web Audio are not tainted — origin must send ACAO for * or this origin.
	 */
	window.applyAnonymousCORSIfNeeded = function (mediaEl, resolvedSrc) {
		if (!mediaEl || resolvedSrc == null || resolvedSrc === '') return;
		var absolute = String(resolvedSrc);
		try {
			absolute = new URL(resolvedSrc, window.location.href).href;
		} catch (e1) { /* keep string */ }
		var remote = false;
		try {
			remote = new URL(absolute).origin !== window.location.origin;
		} catch (e2) {
			remote = false;
		}
		if (remote) {
			mediaEl.crossOrigin = 'anonymous';
		} else if (mediaEl.removeAttribute) {
			mediaEl.removeAttribute('crossorigin');
		} else {
			mediaEl.crossOrigin = null;
		}
	};

	if (window.__INITIAL_PRODUCTION_FILES__ && typeof window.__INITIAL_PRODUCTION_FILES__ === 'object') {
		applyProductionFilesSettings(window.__INITIAL_PRODUCTION_FILES__);
	}
})(window);
