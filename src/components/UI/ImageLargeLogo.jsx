import { Link } from '@components/Navigation/Link';

export function ImageLargeLogo() {
  return (
    <div className="text-center mt-5">
		<h1 className="display-4 bruno-ace noselect">Free TV</h1>
		<p className="pb-4">
			<Link href="/" className="m-0 p-0">
				<img src="/src/assets/freetv.png" width="175" title="Watch Free TV!" alt="Watch Free TV!" />
			</Link>
		</p>
	</div>
  );
}