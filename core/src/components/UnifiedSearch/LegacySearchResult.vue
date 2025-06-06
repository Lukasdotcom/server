<!--
 - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<a :href="resourceUrl || '#'"
		class="unified-search__result"
		:class="{
			'unified-search__result--focused': focused,
		}"
		@click="reEmitEvent"
		@focus="reEmitEvent">

		<!-- Icon describing the result -->
		<div class="unified-search__result-icon"
			:class="{
				'unified-search__result-icon--rounded': rounded,
				'unified-search__result-icon--no-preview': !hasValidThumbnail && !loaded,
				'unified-search__result-icon--with-thumbnail': hasValidThumbnail && loaded,
				[icon]: !loaded && !isIconUrl,
			}"
			:style="{
				backgroundImage: isIconUrl ? `url(${icon})` : '',
			}">

			<img v-if="hasValidThumbnail"
				v-show="loaded"
				:src="thumbnailUrl"
				alt=""
				@error="onError"
				@load="onLoad">
		</div>

		<!-- Title and sub-title -->
		<span class="unified-search__result-content">
			<span class="unified-search__result-line-one" :title="title">
				<NcHighlight :text="title" :search="query" />
			</span>
			<span v-if="subline" class="unified-search__result-line-two" :title="subline">{{ subline }}</span>
		</span>
	</a>
</template>

<script>
import NcHighlight from '@nextcloud/vue/components/NcHighlight'

export default {
	name: 'LegacySearchResult',

	components: {
		NcHighlight,
	},

	props: {
		thumbnailUrl: {
			type: String,
			default: null,
		},
		title: {
			type: String,
			required: true,
		},
		subline: {
			type: String,
			default: null,
		},
		resourceUrl: {
			type: String,
			default: null,
		},
		icon: {
			type: String,
			default: '',
		},
		rounded: {
			type: Boolean,
			default: false,
		},
		query: {
			type: String,
			default: '',
		},

		/**
		 * Only used for the first result as a visual feedback
		 * so we can keep the search input focused but pressing
		 * enter still opens the first result
		 */
		focused: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			hasValidThumbnail: this.thumbnailUrl && this.thumbnailUrl.trim() !== '',
			loaded: false,
		}
	},

	computed: {
		isIconUrl() {
			// If we're facing an absolute url
			if (this.icon.startsWith('/')) {
				return true
			}

			// Otherwise, let's check if this is a valid url
			try {
				// eslint-disable-next-line no-new
				new URL(this.icon)
			} catch {
				return false
			}
			return true
		},
	},

	watch: {
		// Make sure to reset state on change even when vue recycle the component
		thumbnailUrl() {
			this.hasValidThumbnail = this.thumbnailUrl && this.thumbnailUrl.trim() !== ''
			this.loaded = false
		},
	},

	methods: {
		reEmitEvent(e) {
			this.$emit(e.type, e)
		},

		/**
		 * If the image fails to load, fallback to iconClass
		 */
		onError() {
			this.hasValidThumbnail = false
		},

		onLoad() {
			this.loaded = true
		},
	},
}
</script>

<style lang="scss" scoped>
@use "sass:math";

$clickable-area: 44px;
$margin: 10px;

.unified-search__result {
	display: flex;
	align-items: center;
	height: $clickable-area;
	padding: $margin;
	border: 2px solid transparent;
	border-radius: var(--border-radius-large) !important;

	&--focused {
		background-color: var(--color-background-hover);
	}

	&:active,
	&:hover,
	&:focus {
		background-color: var(--color-background-hover);
		border: 2px solid var(--color-border-maxcontrast);
	}

	* {
		cursor: pointer;
	}

	&-icon {
		overflow: hidden;
		width: $clickable-area;
		height: $clickable-area;
		border-radius: var(--border-radius);
		background-repeat: no-repeat;
		background-position: center center;
		background-size: 32px;
		&--rounded {
			border-radius: math.div($clickable-area, 2);
		}
		&--no-preview {
			background-size: 32px;
		}
		&--with-thumbnail {
			background-size: cover;
		}
		&--with-thumbnail:not(&--rounded) {
			// compensate for border
			max-width: $clickable-area - 2px;
			max-height: $clickable-area - 2px;
			border: 1px solid var(--color-border);
		}

		img {
			// Make sure to keep ratio
			width: 100%;
			height: 100%;

			object-fit: cover;
			object-position: center;
		}
	}

	&-icon,
	&-actions {
		flex: 0 0 $clickable-area;
	}

	&-content {
		display: flex;
		align-items: center;
		flex: 1 1 100%;
		flex-wrap: wrap;
		// Set to minimum and gro from it
		min-width: 0;
		padding-inline-start: $margin;
	}

	&-line-one,
	&-line-two {
		overflow: hidden;
		flex: 1 1 100%;
		margin: 1px 0;
		white-space: nowrap;
		text-overflow: ellipsis;
		// Use the same color as the `a`
		color: inherit;
		font-size: inherit;
	}
	&-line-two {
		opacity: .7;
		font-size: var(--default-font-size);
	}
}

</style>
