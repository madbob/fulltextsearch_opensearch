<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-FileCopyrightText: 2026 Roberto Guido
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection
		v-show="visible"
		:name="t('fulltextsearch_opensearch', 'Open Search')">
		<NcFormBox>
			<NcTextField
				v-model="config.opensearch_host"
				:label="t('fulltextsearch_opensearch', 'Address of the OpenSearch server')"
				placeholder="http://username:password@localhost:9200/"
				@blur="saveSettings" />

			<NcCheckboxRadioSwitch
				v-model="config.allow_self_signed_cert"
				@update:modelValue="saveSettings">
				{{ t('fulltextsearch_opensearch', 'Allow self signed certificate when connecting to OpenSearch.') }}
			</NcCheckboxRadioSwitch>

			<NcTextField
				v-model="config.opensearch_index"
				:label="t('fulltextsearch_opensearch', 'Index')"
				:helperText="t('fulltextsearch_opensearch', 'Name of your index.')"
				placeholder="my_index"
				@blur="saveSettings" />

			<NcTextField
				v-model="config.analyzer_tokenizer"
				:label="t('fulltextsearch_opensearch', '[Advanced] Analyzer tokenizer')"
				:helperText="t('fulltextsearch_opensearch', 'Some language might need a specific tokenizer.')"
				@blur="saveSettings" />

			<NcCheckboxRadioSwitch
				v-model="config.opensearch_logger_enabled"
				@update:modelValue="saveSettings">
				{{ t('fulltextsearch_opensearch', 'Enable debug logging.') }}
			</NcCheckboxRadioSwitch>
		</NcFormBox>
	</NcSettingsSection>
</template>

<script setup>

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { NcCheckboxRadioSwitch, NcFormBox, NcSettingsSection, NcTextField } from '@nextcloud/vue'
import { OPENSEARCH_PLATFORM_ID, SETTINGS_UPDATED_EVENT } from './constants.js'
import { logger } from './logger.js'

const config = ref(loadState('fulltextsearch_opensearch', 'adminConfig'))
const visible = ref(window.OCA?.FullTextSearch?.settings?.platform === OPENSEARCH_PLATFORM_ID)

function onSettingsUpdated(detail) {
	visible.value = detail.platform === OPENSEARCH_PLATFORM_ID
}

function handleSettingsUpdatedEvent(event) {
	onSettingsUpdated(event.detail)
}

onMounted(() => {
	window.addEventListener(SETTINGS_UPDATED_EVENT, handleSettingsUpdatedEvent)
})

onBeforeUnmount(() => {
	window.removeEventListener(SETTINGS_UPDATED_EVENT, handleSettingsUpdatedEvent)
})

async function saveSettings() {
	try {
		const { data } = await axios.post(generateUrl('/apps/fulltextsearch_opensearch/admin/settings'), {
			data: {
				opensearch_host: config.value.opensearch_host,
				opensearch_index: config.value.opensearch_index,
				analyzer_tokenizer: config.value.analyzer_tokenizer,
				allow_self_signed_cert: config.value.allow_self_signed_cert,
				opensearch_logger_enabled: config.value.opensearch_logger_enabled,
			},
		})
		config.value = data
	} catch (error) {
		logger.error('Failed to save OpenSearch settings', { error })
	}
}

</script>
