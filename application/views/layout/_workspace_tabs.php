<?php
/**
 * Reusable navigation for a group of linked pages.
 *
 * Inputs:
 * - $workspace_tabs: [['label' => string, 'url' => string, 'active' => bool]]
 * - $workspace_tab_label: short visual group label
 * - $workspace_tab_aria_label: accessible navigation name
 *
 * This intentionally uses links and aria-current, because every item changes
 * page/query state. Bootstrap's tab widget is reserved for content that stays
 * on the same page.
 */
$workspaceTabs = is_array($workspace_tabs ?? null) ? $workspace_tabs : [];
$workspaceTabLabel = trim((string)($workspace_tab_label ?? 'Workspace'));
$workspaceTabAriaLabel = trim((string)($workspace_tab_aria_label ?? 'Navigasi workspace'));
$workspaceTabEscape = static function ($value): string {
    if (function_exists('html_escape')) {
        return html_escape((string)$value);
    }
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<?php if ($workspaceTabs !== []): ?>
  <nav class="finance-workspace-tabs" aria-label="<?php echo $workspaceTabEscape($workspaceTabAriaLabel); ?>">
    <span class="finance-workspace-tabs__label"><?php echo $workspaceTabEscape($workspaceTabLabel); ?></span>
    <div class="finance-workspace-tabs__list" role="list">
      <?php foreach ($workspaceTabs as $workspaceTab): ?>
        <?php
          $workspaceTabIsActive = !empty($workspaceTab['active']);
          $workspaceTabText = (string)($workspaceTab['label'] ?? 'Halaman');
          $workspaceTabUrl = (string)($workspaceTab['url'] ?? '#');
        ?>
        <a
          class="finance-workspace-tabs__link<?php echo $workspaceTabIsActive ? ' is-active' : ''; ?>"
          href="<?php echo $workspaceTabEscape($workspaceTabUrl); ?>"<?php echo $workspaceTabIsActive ? ' aria-current="page"' : ''; ?>
          role="listitem"
        ><?php echo $workspaceTabEscape($workspaceTabText); ?></a>
      <?php endforeach; ?>
    </div>
  </nav>
<?php endif; ?>
