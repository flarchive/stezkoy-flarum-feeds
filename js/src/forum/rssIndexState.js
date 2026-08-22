import app from 'flarum/forum/app';

export const RSS_INDEX_PREFERENCE_KEY = 'stezkoyRssShowOnIndex';

let rssIndexOverride = null;

export function globalRssIndexEnabled() {
  return !!app.forum.attribute('stezkoy-rss.show_on_index');
}

export function userPrefersRssIndex() {
  const user = app.session.user;

  if (!user) return true;

  return user.preferences()?.[RSS_INDEX_PREFERENCE_KEY] !== false;
}

export function rssIndexEnabled() {
  if (!globalRssIndexEnabled()) return false;

  return rssIndexOverride === null ? userPrefersRssIndex() : rssIndexOverride;
}

export function setRssIndexOverride(value) {
  rssIndexOverride = value;
}

export function resetRssIndexOverride() {
  rssIndexOverride = null;
}
