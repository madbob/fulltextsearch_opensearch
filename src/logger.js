import { getLoggerBuilder } from '@nextcloud/logger'

export const logger = getLoggerBuilder().setApp('fulltextsearch_opensearch').detectUser().build()
