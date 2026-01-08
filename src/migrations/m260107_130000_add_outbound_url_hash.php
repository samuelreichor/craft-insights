<?php

namespace samuelreichor\insights\migrations;

use craft\db\Migration;
use samuelreichor\insights\Constants;

/**
 * m260107_130000_add_outbound_url_hash migration.
 *
 * Adds urlHash column to existing outbound table (fixes MySQL key length issue).
 */
class m260107_130000_add_outbound_url_hash extends Migration
{
    public function safeUp(): bool
    {
        // Check if table exists
        if (!$this->db->tableExists(Constants::TABLE_OUTBOUND)) {
            return true;
        }

        // Check if urlHash column already exists
        $columns = $this->db->getTableSchema(Constants::TABLE_OUTBOUND)->columnNames;
        if (in_array('urlHash', $columns, true)) {
            return true;
        }

        // Add urlHash column
        $this->addColumn(
            Constants::TABLE_OUTBOUND,
            'urlHash',
            $this->char(32)->notNull()->defaultValue('')->after('sourceUrl')
        );

        // Populate urlHash for existing rows
        $this->execute("UPDATE " . Constants::TABLE_OUTBOUND . " SET [[urlHash]] = MD5(CONCAT([[targetUrl]], [[sourceUrl]]))");

        // Drop old unique index if exists (may fail silently)
        try {
            $this->dropIndex('insights_outbound_siteId_date_hour_targetUrl_sourceUrl_unq_idx', Constants::TABLE_OUTBOUND);
        } catch (\Throwable) {
            // Index might not exist or have different name
        }

        // Create new unique index with urlHash
        $this->createIndex(null, Constants::TABLE_OUTBOUND, ['siteId', 'date', 'hour', 'urlHash'], true);

        return true;
    }

    public function safeDown(): bool
    {
        if (!$this->db->tableExists(Constants::TABLE_OUTBOUND)) {
            return true;
        }

        $columns = $this->db->getTableSchema(Constants::TABLE_OUTBOUND)->columnNames;
        if (in_array('urlHash', $columns, true)) {
            $this->dropColumn(Constants::TABLE_OUTBOUND, 'urlHash');
        }

        return true;
    }
}
