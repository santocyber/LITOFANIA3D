#!/usr/bin/env python3
import sys, os, argparse
import numpy as np
from PIL import Image, ImageOps, ImageEnhance
import trimesh
from trimesh.util import concatenate

def parse_args():
    p = argparse.ArgumentParser(
        description="Gera litofania 3D: planar, semi ou full (1+ imagens em fatias)"
    )
    p.add_argument("images", help="Uma ou várias imagens separadas por vírgula")
    p.add_argument("stl_out", help="Arquivo STL de saída")
    p.add_argument("glb_out", help="Arquivo GLB de saída")
    for name, tp, default in [
        ("resolution",     float, 0.25),
        ("sphere_diameter",float, 140.0),
        ("angular_width",  float, 360.0),
        ("angular_height", float, 180.0),  # só usado em semi
        ("thickness_max",  float, 2.7),
        ("thickness_min",  float, 0.6),
        ("img_width",      int,   200),
        ("img_height",     int,   200),
        ("exposure_level", float, 1.0),
        ("saturation_level",float, 1.0),
        ("offset_phi",     float, 0.0),
        ("offset_theta",   float, 0.0),
    ]:
        p.add_argument(f"--{name}", type=tp, default=default)
    for flag in ["flip_image","mirror_image","crop_image"]:
        p.add_argument(f"--{flag}", action="store_true")
    p.add_argument(
        "--mode", choices=["planar","semi","full"],
        default="planar", help="Tipo de litofania"
    )
    return p.parse_args()

def preprocess_image(path, args):
    img = Image.open(path).convert("RGB")
    if args.flip_image:   img = ImageOps.mirror(img)
    if args.mirror_image: img = ImageOps.flip(img)
    if args.crop_image:
        w,h = img.size; s=min(w,h)
        img = img.crop(((w-s)//2,(h-s)//2,(w+s)//2,(h+s)//2))
    img = img.resize((args.img_width,args.img_height), Image.LANCZOS)
    img = ImageEnhance.Color(img).enhance(args.saturation_level)
    img = ImageEnhance.Brightness(img).enhance(args.exposure_level)
    return img.convert("L")

def compute_thickness(gray, args):
    arr = np.asarray(gray, dtype=np.float32)/255.0
    return args.thickness_min + (1.0 - arr)*(args.thickness_max - args.thickness_min)

def build_planar(thk, args):
    h,w = thk.shape
    xs = np.linspace(0, w*args.resolution, w)
    ys = np.linspace(0, h*args.resolution, h)
    verts, faces = [], []
    for j in range(h):
        for i in range(w):
            verts.append([xs[i], ys[j], thk[j,i]])
    for j in range(h-1):
        for i in range(w-1):
            a=j*w+i; b=a+1; c=(j+1)*w+i; d=c+1
            faces += [[a,c,b],[b,c,d]]
    return trimesh.Trimesh(np.array(verts), np.array(faces), process=True)

def build_spherical_segment_full(thk, args, theta_start, theta_width):
    """
    Gera um segmento de litofania mapeado em superfície esférica,
    cobrindo de polo a polo (180°), de theta_start a theta_start+theta_width.
    """
    h,w = thk.shape
    r0 = args.sphere_diameter/2.0
    aw = np.deg2rad(theta_width)
    ah = np.deg2rad(180.0)  # sempre cobre 0–180°
    oφ = np.deg2rad(args.offset_phi)
    oθ = np.deg2rad(theta_start) + np.deg2rad(args.offset_theta)

    verts, faces = [], []
    # vértices
    for j in range(h):
        φ = oφ + (j/(h-1))*ah
        for i in range(w):
            θ = oθ + (i/(w-1))*aw
            rr = r0 + thk[j,i]
            verts.append([
                rr * np.sin(φ) * np.cos(θ),
                rr * np.sin(φ) * np.sin(θ),
                rr * np.cos(φ)
            ])
    # faces com wrap horizontal
    for j in range(h-1):
        for i in range(w):
            a = j*w + i
            b = j*w + ((i+1) % w)
            c = (j+1)*w + i
            d = (j+1)*w + ((i+1) % w)
            faces.append([a,c,b])
            faces.append([b,c,d])
    return trimesh.Trimesh(np.array(verts), np.array(faces), process=True)

def main():
    args = parse_args()
    imgs = args.images.split(",")

    if args.mode in ["planar","semi"] and len(imgs)!=1:
        print("Para planar/semi use exatamente 1 imagem.", file=sys.stderr)
        sys.exit(1)

    meshes = []

    if args.mode == "planar":
        gray = preprocess_image(imgs[0], args)
        thk  = compute_thickness(gray, args)
        meshes.append(build_planar(thk, args))

    elif args.mode == "semi":
        gray = preprocess_image(imgs[0], args)
        thk  = compute_thickness(gray, args)
        meshes.append(build_spherical_segment_full(
            thk, args,
            theta_start=0,
            theta_width=args.angular_width
        ))

    # full
    else:
        imgs = args.images.split(",")
        N = len(imgs)
        # força total 360° aqui:
        slice_angle = 360.0 / N

        for k, path in enumerate(imgs):
            gray = preprocess_image(path, args)
            thk  = compute_thickness(gray, args)
            seg  = build_spherical_segment_full(
                thk, args,
                theta_start = k * slice_angle,
                theta_width = slice_angle
            )
            meshes.append(seg)


    # concatena
    mesh = concatenate(meshes)

    # garante saída
    d = os.path.dirname(args.stl_out)
    if d and not os.path.isdir(d): os.makedirs(d, exist_ok=True)

    # exporta
    mesh.export(args.stl_out)
    mesh.export(args.glb_out)

if __name__=="__main__":
    main()
