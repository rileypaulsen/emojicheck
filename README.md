# Slack EmojiCheck

This is a small Slack integration that adds Action handlers (message-based shortcuts in the ellipsis menu of a message) to help identify a list of either "unique" or "missing" people based on whether they have emoji reacted to a given message.

## Use

Open the context menu (ellipsis button) of a message and locate the "**EmojiCheck: Uniques**" or "**EmojiCheck: Missing**" options in the listing. Selecting one of these options will send an ephemeral message in the channel that only the initiating user will be able to see.

### Uniques

Checks through all the emojis for a post and tallies up the users who reacted. Users who reacted with multiple emojis will only be counted once.

### Missing

Identifies all of the unique reactors to a message and subtracts them from the channel's membership list to identify the people who have not yet reacted.

## Setup

1. Create a Slack App
2. Copy the Verification Token and add it to the `secret.php` file and redeploy the code
3. Enable and setup "Shortcuts" for the app. Add one called "**EmojiCheck: Missing**" with a Callback ID of `emojicheck-missing` and a second called "**EmojiCheck: Uniques**" with a Callback ID of `emojicheck-uniques` and point both to the PHP file on a public webserver with SSL.
4. Add the following OAuth permissions: `channels:read`, `chat:write:bot`, `commands`, `reactions:read`, `users:read`
5. Copy the OAuth token and add it to the `secret.php` file and redeploy the code
6. Install/Reinstall the Slack App to your Workspace as necessary