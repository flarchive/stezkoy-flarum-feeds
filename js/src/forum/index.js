import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import IndexPage from 'flarum/forum/components/IndexPage';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import LinkButton from 'flarum/common/components/LinkButton';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import FieldSet from 'flarum/common/components/FieldSet';
import MixedDiscussionList, { isPlainDiscussionIndex, shouldMixRssIntoDiscussionList } from './components/MixedDiscussionList';
import RssSubmitModal from './components/RssSubmitModal';
import { globalRssIndexEnabled, resetRssIndexOverride, RSS_INDEX_PREFERENCE_KEY, rssIndexEnabled, setRssIndexOverride } from './rssIndexState';

export { default as extend } from './extend';

function rssSettingsSwitch(page) {
  const user = page.user || app.session.user;

  if (!user) return null;

  return (
    <Switch
      state={user.preferences()?.[RSS_INDEX_PREFERENCE_KEY] !== false}
      onchange={(value) => {
        page.rssPreferenceLoading = true;

        user
          .savePreferences({
            [RSS_INDEX_PREFERENCE_KEY]: value,
          })
          .then(() => {
            resetRssIndexOverride();
            app.discussions.refresh();
          })
          .finally(() => {
            page.rssPreferenceLoading = false;
            m.redraw();
          });
      }}
      loading={page.rssPreferenceLoading}
    >
      {app.translator.trans('stezkoy-rss.forum.settings.show_on_index_label')}
      <span className="helpText">{app.translator.trans('stezkoy-rss.forum.settings.show_on_index_help')}</span>
    </Switch>
  );
}

function addRssSettingsSection(SettingsPage) {
  if (!SettingsPage?.prototype || SettingsPage.prototype.stezkoyRssSettingsExtended) return;

  SettingsPage.prototype.stezkoyRssSettingsExtended = true;

  extend(SettingsPage.prototype, 'settingsItems', function (items) {
    if (!globalRssIndexEnabled()) return;

    const switchItem = rssSettingsSwitch(this);

    if (!switchItem) return;

    items.add(
      'rss',
      <FieldSet
        className="Settings-rss FieldSet--min"
        label={app.translator.trans('stezkoy-rss.forum.settings.heading')}
      >
        {[switchItem]}
      </FieldSet>,
      65
    );
  });
}

function extendSettingsRoute() {
  const route = app.routes.settings;

  if (!route?.component || route.stezkoyRssSettingsRouteExtended) return;

  const originalComponent = route.component;

  route.stezkoyRssSettingsRouteExtended = true;
  route.component = () => {
    const component = typeof originalComponent === 'function' && originalComponent.prototype ? { default: originalComponent } : originalComponent();

    return Promise.resolve(component).then((module) => {
      const SettingsPage = module.default || module;

      addRssSettingsSection(SettingsPage);

      return {
        ...module,
        default: SettingsPage,
      };
    });
  };
}

app.initializers.add('stezkoy-rss', () => {
  extendSettingsRoute();

  extend(IndexSidebar.prototype, 'navItems', (items) => {
    if (rssIndexEnabled()) return;

    items.add(
      'rss',
      <LinkButton href={app.route('rss.feed')} icon="fas fa-rss">
        {app.translator.trans('stezkoy-rss.forum.rss_link')}
      </LinkButton>,
      90
    );
  });

  extend(IndexPage.prototype, 'contentItems', (items) => {
    if (shouldMixRssIntoDiscussionList(app.discussions) && items.has('discussionList')) {
      items.setContent('discussionList', <MixedDiscussionList state={app.discussions} />);
    }
  });

  extend(IndexPage.prototype, 'actionItems', function (items) {
    if (!globalRssIndexEnabled() || !isPlainDiscussionIndex(app.discussions)) return;

    const enabled = rssIndexEnabled();
    const page = this;

    items.add(
      'rssToggle',
      <Button
        title={app.translator.trans(enabled ? 'stezkoy-rss.forum.index.switch_to_native_tooltip' : 'stezkoy-rss.forum.index.switch_to_rss_tooltip')}
        aria-label={app.translator.trans(enabled ? 'stezkoy-rss.forum.index.switch_to_native_tooltip' : 'stezkoy-rss.forum.index.switch_to_rss_tooltip')}
        icon={enabled ? 'fas fa-list' : 'fas fa-rss'}
        className="Button Button--icon"
        active={enabled}
        onclick={() => {
          if (page.rssPreferenceLoading) return;

          const value = !enabled;
          const user = app.session.user;

          if (!user) {
            setRssIndexOverride(value);
            app.discussions.refresh();
            m.redraw();
            return;
          }

          page.rssPreferenceLoading = true;

          user
            .savePreferences({
              [RSS_INDEX_PREFERENCE_KEY]: value,
            })
            .then(() => {
              resetRssIndexOverride();
              app.discussions.refresh();
            })
            .finally(() => {
              page.rssPreferenceLoading = false;
              m.redraw();
            });
        }}
      />,
      110
    );

    items.add(
      'rssSubmit',
      <Button
        title={app.translator.trans('stezkoy-rss.forum.index.submit_feed_tooltip')}
        aria-label={app.translator.trans('stezkoy-rss.forum.index.submit_feed_tooltip')}
        icon="fas fa-plus"
        className="Button Button--icon"
        onclick={() => app.modal.show(RssSubmitModal)}
      />,
      105
    );
  });
});
