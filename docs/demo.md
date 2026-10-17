# The demo

Dog-ear Books is a made-up independent bookshop, built to show Social Images. It has two sections: **Books**, a catalogue whose images use a code template showing each cover (Book jacket), and **Journal**, posts with writers, dates and pictures, whose images use the Editorial starter template.

It's in the [social-images-craft-demo](https://github.com/1994limited/social-images-craft-demo) repository and runs on a local Craft setup (DDEV, Herd or similar) that meets the [requirements](installation.md#requirements).

To set it up (cloning it, installing its dependencies, loading its content and signing in), see the demo's README. It also says how rendering is set up; see [Rendering](rendering.md) to use Browsershot with your own Chrome, or Cloudflare.

Once it's running, make the images with **Social Images → Images → Generate missing**, or `php craft social-images/generate`.
