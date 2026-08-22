import Extend from 'flarum/common/extenders';
import RssFeed from './models/RssFeed';
import RssSettingsPage from './components/RssSettingsPage';

export default [
  new Extend.Store().add('rss-feeds', RssFeed),

  new Extend.Admin().page(RssSettingsPage),
];
